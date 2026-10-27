<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineDbal\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\AbstractLogger;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Local\SealedBoxKms;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[RequiresPhpExtension('pdo_sqlite')]
class DataKeyStoreTest extends TestCase
{
    private const string SECRET = 'secret';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public function testCreateTableThenStoreAndReadBackAKey()
    {
        $store = $this->store();
        $handle = $store->current('user.email');
        $plaintext = self::plaintextOf($handle);
        $store->forget();

        $this->assertInstanceOf(DataKeyHandle::class, $handle);
        $this->assertSame($plaintext, self::plaintextOf($store->get($handle->reference)), 'the row must survive a round trip through the database.');
    }

    public function testTheTableHoldsSixColumnsAndNothingMore()
    {
        $store = $this->store();
        $store->current('user.email');

        $row = $this->connection->fetchAssociative(\sprintf('SELECT * FROM %s', DataKeyStore::DEFAULT_TABLE));

        $this->assertSame(['id', 'scope', 'key_material', 'master_key_id', 'client', 'binding'], array_keys($row));
    }

    public function testAScopeLongerThanItsColumnIsRefused()
    {
        $store = $this->store();
        $store->current(str_repeat('s', 191));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A scope is at most 191 bytes long, 192 given.');
        $store->current(str_repeat('s', 192));
    }

    public function testRotatingAScopeLongerThanItsColumnIsRefused()
    {
        $store = $this->store();

        $this->expectException(InvalidArgumentException::class);
        $store->rotate(str_repeat('s', 192));
    }

    public function testTheReferenceStoredIsATimeOrderedUuid()
    {
        $store = $this->store();

        $reference = $store->current('user.email')->reference;

        $this->assertSame(16, \strlen($reference));
        $this->assertInstanceOf(UuidV7::class, Uuid::fromBinary($reference));
    }

    public function testTheSameScopeReusesItsRow()
    {
        $store = $this->store();

        $this->assertSame($store->current('user.email')->reference, $store->current('user.email')->reference);
        $this->assertSame(1, $this->rowCount());
    }

    public function testTheCurrentKeyOfAScopeIsResolvedOncePerProcess()
    {
        $store = $this->store();
        $reference = $store->current('user.email')->reference;
        $this->connection->executeStatement(\sprintf('DELETE FROM %s', DataKeyStore::DEFAULT_TABLE));

        $this->assertSame($reference, $store->current('user.email')->reference, 'a scope is not looked up again for every payload it encrypts.');
    }

    public function testForgettingSendsTheNextResolutionBackToTheDatabase()
    {
        $store = $this->store();
        $reference = $store->current('user.email')->reference;
        $store->forget();
        $this->connection->executeStatement(\sprintf('DELETE FROM %s', DataKeyStore::DEFAULT_TABLE));

        $this->assertNotSame($reference, $store->current('user.email')->reference, 'nothing is remembered across a forget().');
    }

    public function testARememberedKeyIsStillDroppedOnceItsAgeIsReached()
    {
        $store = $this->store(maxAgeSeconds: 0);
        $first = $store->current('user.email')->reference;

        $this->assertNotSame($first, $store->current('user.email')->reference, 'remembering a scope must not outlive the retirement it was remembered before.');
    }

    public function testEachScopeGetsItsOwnRow()
    {
        $store = $this->store();
        $store->current('user.email');
        $store->current('user.phone');

        $this->assertSame(2, $this->rowCount());
    }

    public function testTheNewestRowOfAScopeIsTheCurrentOne()
    {
        $store = $this->store();
        $retired = $store->current('user.email')->reference;
        $fresh = $store->rotate('user.email')->reference;
        $store->forget();

        $this->assertSame($fresh, $store->current('user.email')->reference);
        $this->assertSame(2, $this->rowCount(), 'the retired row stays, otherwise its payloads would become unreadable.');
        $this->assertNotSame($retired, $fresh);
    }

    public function testAPayloadWrittenBeforeARotationStillDecrypts()
    {
        $store = $this->store();
        $encrypter = new StoredEnvelopeEncrypter($store);
        $envelope = $encrypter->encrypt('user.email', 'written before');

        $store->rotate('user.email');
        $store->forget();

        $this->assertSame('written before', $encrypter->decrypt($envelope));
    }

    public function testAKeyMintedInARolledBackTransactionIsNotEncryptedWith()
    {
        $store = $this->store();
        $encrypter = new StoredEnvelopeEncrypter($store);

        $this->connection->beginTransaction();
        $store->current('user.email');
        $this->connection->rollBack();

        $envelope = $encrypter->encrypt('user.email', 'after the rollback');
        $store->forget();

        $this->assertSame('after the rollback', $encrypter->decrypt($envelope));
        $this->assertSame(1, $this->rowCount());
    }

    public function testAKeyMintedInARolledBackSavepointIsNotEncryptedWith()
    {
        $store = $this->store();
        $encrypter = new StoredEnvelopeEncrypter($store);

        $this->connection->beginTransaction();
        $this->connection->beginTransaction();
        $store->current('user.email');
        $this->connection->rollBack();
        $envelope = $encrypter->encrypt('user.email', 'after the savepoint');
        $this->connection->commit();
        $store->forget();

        $this->assertSame('after the savepoint', $encrypter->decrypt($envelope));
        $this->assertSame(1, $this->rowCount());
    }

    public function testAKeyMintedInATransactionIsTrustedOnceItsCommitWasSeen()
    {
        $queries = [];
        $this->recordQueries($queries);
        $store = $this->store();

        $this->connection->beginTransaction();
        $store->current('user.email');
        $store->current('user.email');
        $this->connection->commit();
        $queries = [];
        $store->current('user.email');
        $this->assertCount(1, $queries, 'the row is looked up once after the commit.');
        $this->assertStringStartsWith('SELECT 1 FROM', $queries[0]);

        $queries = [];
        $store->current('user.email');
        $store->current('user.email');
        $this->assertSame([], $queries, 'a key seen committed is not looked up again.');
    }

    public function testAReferenceIsResolvedOncePerProcess()
    {
        $queries = [];
        $this->recordQueries($queries);
        $store = $this->store();
        $reference = $store->current('user.email')->reference;
        $store->forget();

        $queries = [];
        $store->get($reference);
        $this->assertCount(1, $queries, 'the first read goes to the database.');

        $queries = [];
        $store->get($reference);
        $store->get($reference);
        $this->assertSame([], $queries, 'a reference already resolved is not looked up again for every value it opens.');
    }

    public function testTheKeyHeldForAScopeIsHandedBackByReferenceWithoutAQuery()
    {
        $queries = [];
        $this->recordQueries($queries);
        $store = $this->store();
        $reference = $store->current('user.email')->reference;

        $queries = [];
        $this->assertSame(self::plaintextOf($store->current('user.email')), self::plaintextOf($store->get($reference)));
        $this->assertSame([], $queries, 'the key a scope encrypts with is the same object its reference resolves to.');
    }

    public function testOpeningSeventeenValuesSealedUnderOneScopeReadsItsRowOnce()
    {
        $queries = [];
        $this->recordQueries($queries);
        $store = $this->store();
        $encrypter = new StoredEnvelopeEncrypter($store);

        $envelopes = [];
        for ($i = 0; $i < 17; ++$i) {
            $envelopes[] = $encrypter->encrypt('user', \sprintf('value %d', $i));
        }
        $store->forget();

        $queries = [];
        foreach ($envelopes as $i => $envelope) {
            $this->assertSame(\sprintf('value %d', $i), $encrypter->decrypt($envelope));
        }

        $this->assertCount(1, $queries, 'a data key is read once per process, not once per sealed value opened.');
    }

    public function testAReferenceDestroyedUnderneathTheProcessIsOpenedUntilItIsForgotten()
    {
        $store = $this->store();
        $reference = $store->current('user.email')->reference;
        $plaintext = self::plaintextOf($store->get($reference));
        $this->connection->executeStatement(\sprintf('DELETE FROM %s', DataKeyStore::DEFAULT_TABLE));

        $this->assertSame($plaintext, self::plaintextOf($store->get($reference)), 'a process already holding the plaintext keeps it, the way a remembered scope does.');

        $store->forget();

        $this->expectException(DataKeyNotFoundException::class);
        $store->get($reference);
    }

    public function testAKeyMintedInARolledBackTransactionIsNotOpenedByReferenceEither()
    {
        $store = $this->store();

        $this->connection->beginTransaction();
        $reference = $store->current('user.email')->reference;
        $store->get($reference);
        $this->connection->rollBack();

        $this->expectException(DataKeyNotFoundException::class);
        $store->get($reference);
    }

    public function testAZeroMaxAgeRotatesOnEveryCall()
    {
        $store = $this->store(maxAgeSeconds: 0);

        $this->assertNotSame($store->current('user.email')->reference, $store->current('user.email')->reference);
        $this->assertSame(2, $this->rowCount());
    }

    public function testAnUnreachedMaxAgeDoesNotRotate()
    {
        $store = $this->store(maxAgeSeconds: 3600);

        $this->assertSame($store->current('user.email')->reference, $store->current('user.email')->reference);
        $this->assertSame(1, $this->rowCount());
    }

    public function testAStoreThatWasToldNothingRetiresAKeyOlderThanTheDefaultAge()
    {
        $store = new DataKeyStore($this->connection, new ServiceLocator(['default' => static fn (): object => new InMemoryKms()]), 'default', 'app', self::SECRET);
        $store->createTable();

        $aged = $this->age($store, 'user.email', DataKeyStore::DEFAULT_MAX_AGE_SECONDS + 86400);
        $store->forget();

        $this->assertNotSame($aged, $store->current('user.email')->reference);
        $this->assertSame(2, $this->rowCount());
    }

    public function testAKeyYoungerThanTheDefaultAgeIsKept()
    {
        $store = $this->store();

        $kept = $this->age($store, 'user.email', DataKeyStore::DEFAULT_MAX_AGE_SECONDS - 86400);
        $store->forget();

        $this->assertSame($kept, $store->current('user.email')->reference);
        $this->assertSame(1, $this->rowCount());
    }

    public function testANullMaxAgeNeverRotates()
    {
        $store = $this->store(maxAgeSeconds: null);

        $kept = $this->age($store, 'user.email', 10 * DataKeyStore::DEFAULT_MAX_AGE_SECONDS);
        $store->forget();

        $this->assertSame($kept, $store->current('user.email')->reference);
        $this->assertSame(1, $this->rowCount());
    }

    public function testTheClockDatesTheReference()
    {
        $clock = new MockClock('2020-01-01 00:00:00');
        $store = $this->store(clock: $clock);

        $this->assertSame($clock->now()->getTimestamp(), Uuid::fromBinary($store->current('user.email')->reference)->getDateTime()->getTimestamp());
    }

    public function testTheClockTellsWhenAKeyRetires()
    {
        $clock = new MockClock('2020-01-01 00:00:00');
        $store = $this->store(maxAgeSeconds: 3600, clock: $clock);
        $first = $store->current('user.email')->reference;

        $clock->sleep(3599);
        $this->assertSame($first, $store->current('user.email')->reference);

        $clock->sleep(1);
        $second = $store->current('user.email')->reference;
        $this->assertNotSame($first, $second, 'the remembered key retires on the time of the clock.');

        $clock->sleep(3600);
        $store->forget();
        $this->assertNotSame($second, $store->current('user.email')->reference, 'the stored key retires on the time of the clock.');
        $this->assertSame(3, $this->rowCount());
    }

    public function testKeysMintedAtTheSameInstantKeepTheirOrder()
    {
        $store = $this->store(clock: new MockClock('2020-01-01 00:00:00'));
        $references = [$store->current('user.email')->reference, $store->rotate('user.email')->reference, $store->rotate('user.email')->reference];
        $store->forget();

        $this->assertSame($references[2], $store->current('user.email')->reference);
        $this->assertSame($references, array_map(static fn (StoredDataKey $row): string => $row->reference, iterator_to_array($store->all(), false)));
    }

    #[TimeSensitive(UuidV7::class)]
    public function testTheNewestRowStaysTheCurrentOneWhenTheClockGoesBackwards()
    {
        ClockMock::withClockMock($now = time() + .1239);
        $store = $this->store();
        $store->current('user.email');

        // the clock goes back by 800 microseconds, without leaving the millisecond
        ClockMock::withClockMock($now - .0008);
        $fresh = $store->rotate('user.email')->reference;
        $store->forget();

        $this->assertSame($fresh, $store->current('user.email')->reference);
    }

    public function testAnUnknownReferenceIsReportedAsSuch()
    {
        $store = $this->store();

        $this->expectException(DataKeyNotFoundException::class);
        $store->get(str_repeat("\x00", 16));
    }

    public function testAllListsOldestFirstAndFiltersByClient()
    {
        $store = $this->store();
        $first = $store->current('a')->reference;
        $second = $store->current('b')->reference;

        $references = array_map(static fn (StoredDataKey $row): string => $row->reference, iterator_to_array($store->all(), false));

        $this->assertSame([$first, $second], $references);
        $this->assertCount(2, iterator_to_array($store->all('default'), false));
        $this->assertCount(0, iterator_to_array($store->all('azure'), false));
    }

    public function testRewrapOnAnUnknownReferenceThrows()
    {
        $store = $this->store();
        $plaintext = self::plaintextOf($store->current('user.email'));
        $row = iterator_to_array($store->all(), false)[0];
        $unknown = str_repeat("\x00", 16);
        $binding = StoredDataKey::bindingFor($unknown, $row->scope, $plaintext, self::SECRET);

        $this->expectException(DataKeyNotFoundException::class);
        $store->rewrap(new StoredDataKey($unknown, $row->scope, $row->wrapped, $row->client, $binding), new DataKey($plaintext, $row->wrapped), 'default');
    }

    public function testARowEditedSinceItWasListedIsNotRewrapped()
    {
        $kms = new InMemoryKms();
        $store = $this->store(clients: ['default' => $kms]);
        $plaintext = self::plaintextOf($store->current('user.email'));
        $row = iterator_to_array($store->all(), false)[0];
        $this->connection->executeStatement(\sprintf('UPDATE %s SET scope = ?', DataKeyStore::DEFAULT_TABLE), ['user.phone']);

        $this->expectException(DataKeyNotFoundException::class);
        $store->rewrap($row, new DataKey($plaintext, $kms->encrypt('app', $plaintext)), 'default');
    }

    public function testRewrappingAKeyThatDoesNotMatchItsRowIsRefused()
    {
        $kms = new InMemoryKms();
        $store = $this->store(clients: ['default' => $kms]);
        $store->current('user.email');
        $row = iterator_to_array($store->all(), false)[0];
        $before = $this->connection->fetchAssociative('SELECT * FROM key_management_data_keys');
        $planted = str_repeat('A', 32);

        try {
            $store->rewrap($row, new DataKey($planted, $kms->encrypt('app', $planted)), 'default');
            $this->fail('A key that does not match the binding of its row must be refused.');
        } catch (DecryptionFailedException) {
        }

        $this->assertSame($before, $this->connection->fetchAssociative('SELECT * FROM key_management_data_keys'));
    }

    public function testRewrappingBindsTheKeyAgainUnderTheFirstSecret()
    {
        $kms = new InMemoryKms();
        $handle = $this->store(clients: ['default' => $kms], secret: 'old')->current('user.email');
        $plaintext = self::plaintextOf($handle);

        $store = $this->reopen(['default' => $kms], ['new', 'old']);
        $store->rewrap(iterator_to_array($store->all(), false)[0], new DataKey($plaintext, $kms->encrypt('app', $plaintext)), 'default');

        $this->assertSame($plaintext, self::plaintextOf($this->reopen(['default' => $kms], 'new')->get($handle->reference)));
    }

    public function testAKeyBoundUnderAPreviousSecretIsStillRead()
    {
        $kms = new InMemoryKms();
        $handle = $this->store(clients: ['default' => $kms], secret: 'old')->current('user.email');

        $this->assertSame(self::plaintextOf($handle), self::plaintextOf($this->reopen(['default' => $kms], ['new', 'old'])->get($handle->reference)));

        $this->expectException(DecryptionFailedException::class);
        $this->reopen(['default' => $kms], 'new')->get($handle->reference);
    }

    public function testANewKeyIsBoundUnderTheFirstSecret()
    {
        $kms = new InMemoryKms();
        $handle = $this->store(clients: ['default' => $kms], secret: ['new', 'old'])->current('user.email');

        $this->assertSame(self::plaintextOf($handle), self::plaintextOf($this->reopen(['default' => $kms], 'new')->get($handle->reference)));
    }

    #[TestWith([''])]
    #[TestWith([[]])]
    #[TestWith([['secret', '']])]
    public function testAStoreNeedsANonEmptySecret(string|array $secret)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->reopen([], $secret);
    }

    #[RequiresPhpExtension('openssl')]
    public function testAKeyWrappedUnderAMasterKeyOfTheAttackerIsRefused()
    {
        $attackerKey = random_bytes(32);
        $store = $this->store(clients: ['default' => new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32), 'shared-by-the-attacker' => $attackerKey]))]);
        $store->current('user.email');
        $store->forget();
        $planted = str_repeat('A', 32);
        $wrapped = new OpenSslKms(new InMemoryKeyLoader(['shared-by-the-attacker' => $attackerKey]))->encrypt('shared-by-the-attacker', $planted);
        $reference = $this->plant('user.email', $wrapped, $planted);

        try {
            $store->current('user.email');
            $this->fail('A key wrapped by the attacker must not become the current key of its scope.');
        } catch (DecryptionFailedException) {
        }

        $this->expectException(DecryptionFailedException::class);
        $store->get($reference);
    }

    #[RequiresPhpExtension('sodium')]
    public function testAKeyWrappedUnderThePublicKeyOfASealedBoxIsRefused()
    {
        $keypair = sodium_crypto_box_keypair();
        $store = $this->store(clients: ['default' => new SealedBoxKms(new InMemoryKeyLoader(['app' => $keypair]))]);
        $store->current('user.email');
        $store->forget();
        $planted = str_repeat('A', 32);
        $this->plant('user.email', new Ciphertext(sodium_crypto_box_seal($planted, sodium_crypto_box_publickey($keypair)), 'app'), $planted);

        $this->expectException(DecryptionFailedException::class);
        $store->current('user.email');
    }

    #[RequiresPhpExtension('openssl')]
    public function testAProviderMigrationTouchesTheKeysAndNotThePayloads()
    {
        $aws = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $azure = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $store = $this->store(clients: ['aws' => $aws, 'azure' => $azure], client: 'aws');
        $encrypter = new StoredEnvelopeEncrypter($store);

        $envelope = $encrypter->encrypt('user.email', 'survives the migration');
        $frozen = (string) $envelope;

        foreach ($store->all('aws') as $row) {
            $plaintext = $aws->decrypt($row->wrapped);
            $store->rewrap($row, new DataKey($plaintext, $azure->encrypt('app', $plaintext)), 'azure');
        }
        $store->forget();

        $this->assertSame('survives the migration', $encrypter->decrypt($envelope));
        $this->assertSame($frozen, (string) $envelope, 'the payload itself is never rewritten.');
        $this->assertCount(1, iterator_to_array($store->all('azure'), false));
        $this->assertCount(0, iterator_to_array($store->all('aws'), false));
    }

    #[RequiresPhpExtension('openssl')]
    public function testARowClaimingTheWrongClientFailsToUnwrap()
    {
        $store = $this->store(clients: [
            'aws' => new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)])),
            'azure' => new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)])),
        ], client: 'aws');
        $handle = $store->current('user.email');
        $reference = $handle->reference;
        $row = iterator_to_array($store->all(), false)[0];

        $store->rewrap($row, new DataKey(self::plaintextOf($handle), $row->wrapped), 'azure');
        $store->forget();

        $this->expectException(DecryptionFailedException::class);
        $store->get($reference);
    }

    public function testARowCopiedUnderAFreshReferenceNoLongerUnwraps()
    {
        $store = $this->store();
        $store->current('user.email');
        $row = iterator_to_array($store->all(), false)[0];

        $this->connection->insert(DataKeyStore::DEFAULT_TABLE, [
            'id' => Uuid::v7()->toBinary(),
            'scope' => $row->scope,
            'key_material' => $row->wrapped->blob,
            'master_key_id' => $row->wrapped->keyId,
            'client' => $row->client,
            'binding' => $row->binding,
        ], [
            'id' => ParameterType::BINARY,
            'key_material' => ParameterType::BINARY,
            'binding' => ParameterType::BINARY,
        ]);
        $store->forget();

        $this->expectException(DecryptionFailedException::class);
        $store->current('user.email');
    }

    public function testAKeyMovedToAnotherScopeNoLongerUnwraps()
    {
        $store = $this->store();
        $reference = $store->current('user.email')->reference;
        $store->forget();

        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET scope = ? WHERE id = ?', DataKeyStore::DEFAULT_TABLE),
            ['user.phone', $reference],
            [ParameterType::STRING, ParameterType::BINARY],
        );

        $this->expectException(DecryptionFailedException::class);
        $store->get($reference);
    }

    public function testAMissingClientIsReportedLoudly()
    {
        $store = $this->store(client: 'typo');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No KMS client named "typo" is registered on the data key store.');
        $store->current('user.email');
    }

    public function testRewrappingUnderAnUntrustedClientLeavesTheRowUnchanged()
    {
        $store = $this->store();
        $plaintext = self::plaintextOf($store->current('user.email'));
        $row = $this->connection->fetchAssociative('SELECT * FROM key_management_data_keys');

        try {
            $store->rewrap(iterator_to_array($store->all(), false)[0], new DataKey($plaintext, new Ciphertext('untrusted', 'app')), 'untrusted');
            $this->fail('An untrusted client must be refused.');
        } catch (LogicException) {
        }

        $this->assertSame($row, $this->connection->fetchAssociative('SELECT * FROM key_management_data_keys'));
    }

    public function testACachedKeyCannotBeMovedToAnotherScope()
    {
        $store = $this->store();
        $reference = $store->current('user.email')->reference;
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET scope = ? WHERE id = ?', DataKeyStore::DEFAULT_TABLE),
            ['user.phone', $reference],
            [ParameterType::STRING, ParameterType::BINARY],
        );

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $store->current('user.phone');
                $this->fail('A changed scope must be authenticated on every lookup.');
            } catch (DecryptionFailedException) {
            }
        }

        $this->assertSame(1, $this->rowCount());
    }

    public function testACachedKeyCannotUseAnUntrustedClientWhenResolvingAScope()
    {
        $store = $this->store();
        $reference = $store->current('user.email')->reference;
        $store->forget();
        $store->get($reference);
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET client = ? WHERE id = ?', DataKeyStore::DEFAULT_TABLE),
            ['untrusted', $reference],
            [ParameterType::STRING, ParameterType::BINARY],
        );

        $this->expectException(LogicException::class);
        $store->current('user.email');
    }

    public function testAStoreThatWasNotToldToBindAContextWrapsWithNone()
    {
        $store = $this->store(clients: ['default' => new InMemoryKms('aws')]);
        $store->current('user.email');

        $this->assertStringContainsString('/'.bin2hex('').'/', self::keyMaterialOf($this->connection), 'the default is the one every backend can wrap under.');
    }

    public function testAContextBindingStoreWrapsItsKeysUnderTheReferenceAndTheScope()
    {
        $store = $this->store(clients: ['default' => new InMemoryKms('aws')], bindWrappingContext: true);
        $reference = $store->current('user.email')->reference;

        $this->assertStringContainsString(
            '/'.bin2hex(StoredDataKey::wrappingContextFor($reference, 'user.email')).'/',
            self::keyMaterialOf($this->connection),
        );
    }

    public function testAContextBindingStoreUnwrapsUnderTheSameContext()
    {
        $store = $this->store(clients: ['default' => new InMemoryKms('aws')], bindWrappingContext: true);
        $handle = $store->current('user.email');
        $plaintext = self::plaintextOf($handle);
        $store->forget();

        $this->assertSame($plaintext, self::plaintextOf($store->get($handle->reference)));
    }

    /**
     * The binding is recomputed for the scope the row now states on purpose, so that the row is
     * self-consistent and only the backend is left to refuse it.
     */
    public function testAContextBindingStoreIsRefusedAKeyWrappedForAnotherScope()
    {
        $store = $this->store(clients: ['default' => new InMemoryKms('aws')], bindWrappingContext: true);
        $handle = $store->current('user.email');
        $reference = $handle->reference;
        $binding = $handle->use(static fn (#[\SensitiveParameter] string $plaintext): string => StoredDataKey::bindingFor($reference, 'user.phone', $plaintext, self::SECRET));
        $store->forget();

        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET scope = ?, binding = ? WHERE id = ?', DataKeyStore::DEFAULT_TABLE),
            ['user.phone', $binding, $reference],
            [ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY],
        );

        $this->expectException(DecryptionFailedException::class);
        $store->get($reference);
    }

    public function testTheContextAStoreWrapsWithIsWhatItStates()
    {
        $bound = $this->store(clients: ['default' => new InMemoryKms('aws')], bindWrappingContext: true);
        $reference = $bound->current('user.email')->reference;
        $row = iterator_to_array($bound->all(), false)[0];

        $this->assertSame(StoredDataKey::wrappingContextFor($reference, 'user.email'), $bound->wrappingContextOf($row));
        $this->assertSame('', $this->storeWithoutTable()->wrappingContextOf($row), 'a store binding nothing says so, which is what keeps a rewrap from inventing a context.');
    }

    public function testARowWrittenBeforeTheContextIsMovedOverByAnUnboundRewrap()
    {
        $kms = new InMemoryKms('aws');
        $plaintext = self::plaintextOf($this->store(clients: ['default' => $kms])->current('user.email'));

        $bound = new DataKeyStore($this->connection, new ServiceLocator(['default' => static fn (): object => $kms]), 'default', 'app', self::SECRET, DataKeyStore::DEFAULT_TABLE, 32, DataKeyStore::DEFAULT_MAX_AGE_SECONDS, null, true);
        $row = iterator_to_array($bound->all(), false)[0];
        $context = $bound->wrappingContextOf($row);
        $bound->rewrap($row, $kms->unwrapDataKey($row->wrapped)->use(static fn (#[\SensitiveParameter] string $dek): DataKey => new DataKey($dek, $kms->encrypt('app', $dek, $context))), 'default');

        $this->assertSame($plaintext, self::plaintextOf($bound->get($row->reference)));
    }

    public function testACustomTableNameIsHonoured()
    {
        $store = $this->store(table: 'app_deks');
        $store->current('user.email');

        $this->assertTrue($this->connection->createSchemaManager()->tablesExist(['app_deks']));
    }

    public function testConfigureSchemaDescribesTheSixColumnsTheQueriesName()
    {
        $schema = $this->storeWithoutTable()->configureSchema(new Schema(), static fn (): bool => true);

        $table = $schema->getTable(DataKeyStore::DEFAULT_TABLE);
        $types = method_exists($this->connection->getConfiguration(), 'getTypeProvider') ? $this->connection->getConfiguration()->getTypeProvider() : Type::getTypeRegistry();
        $columns = [];
        foreach (['id', 'scope', 'key_material', 'master_key_id', 'client', 'binding'] as $name) {
            $column = $table->getColumn($name);
            $type = method_exists($column, 'getTypeName') ? $types->get($column->getTypeName()) : $column->getType();
            $columns[$name] = [$type::class, $column->getLength()];
        }

        $this->assertSame([
            'id' => [BinaryType::class, 16],
            'scope' => [StringType::class, 191],
            'key_material' => [BlobType::class, null],
            'master_key_id' => [StringType::class, 255],
            'client' => [StringType::class, 64],
            'binding' => [BinaryType::class, 32],
        ], $columns);
        $this->assertCount(6, $table->getColumns(), 'the queries name these six columns and no other.');
    }

    public function testConfigureSchemaKeysTheTableOnItsReferenceAndIndexesTheScopeLookup()
    {
        $schema = $this->storeWithoutTable()->configureSchema(new Schema(), static fn (): bool => true);

        $sql = implode("\n", $schema->toSql($this->connection->getDatabasePlatform()));

        $this->assertTrue($schema->getTable(DataKeyStore::DEFAULT_TABLE)->getColumn('id')->getFixed(), 'a UUID is 16 bytes and never fewer.');
        $this->assertStringContainsString('PRIMARY KEY (id)', $sql);
        $this->assertStringContainsString(\sprintf('CREATE INDEX %1$s_scope_idx ON %1$s (scope, id)', DataKeyStore::DEFAULT_TABLE), $sql, 'the current key of a scope is read with ORDER BY id DESC.');
    }

    public function testConfigureSchemaHonoursACustomTableName()
    {
        $schema = $this->storeWithoutTable('app_deks')->configureSchema(new Schema(), static fn (): bool => true);

        $this->assertTrue($schema->hasTable('app_deks'));
        $this->assertFalse($schema->hasTable(DataKeyStore::DEFAULT_TABLE));
    }

    public function testConfigureSchemaLeavesAnotherDatabaseAlone()
    {
        $schema = $this->storeWithoutTable()->configureSchema(new Schema(), static fn (): bool => false);

        $this->assertFalse($schema->hasTable(DataKeyStore::DEFAULT_TABLE));
    }

    public function testConfigureSchemaDoesNotOverwriteATableTheApplicationAlreadyDeclared()
    {
        if (method_exists(Schema::class, 'edit')) {
            $schema = (new Schema())->edit()->addTable(new Table(DataKeyStore::DEFAULT_TABLE))->create();
        } else {
            $schema = new Schema();
            $schema->createTable(DataKeyStore::DEFAULT_TABLE);
        }

        $schema = $this->storeWithoutTable()->configureSchema($schema, static fn (): bool => true);

        $this->assertSame([], $schema->getTable(DataKeyStore::DEFAULT_TABLE)->getColumns());
    }

    /**
     * @param array<string, object>|null $clients
     */
    private function store(?array $clients = null, string $client = 'default', string $table = DataKeyStore::DEFAULT_TABLE, ?int $maxAgeSeconds = DataKeyStore::DEFAULT_MAX_AGE_SECONDS, ?ClockInterface $clock = null, bool $bindWrappingContext = false, string|array $secret = self::SECRET): DataKeyStore
    {
        $store = $this->reopen($clients ?? ['default' => new InMemoryKms()], $secret, $client, $table, $maxAgeSeconds, $clock, $bindWrappingContext);
        $store->createTable();

        return $store;
    }

    /**
     * @param array<string, object> $clients
     */
    private function reopen(array $clients, string|array $secret, string $client = 'default', string $table = DataKeyStore::DEFAULT_TABLE, ?int $maxAgeSeconds = DataKeyStore::DEFAULT_MAX_AGE_SECONDS, ?ClockInterface $clock = null, bool $bindWrappingContext = false): DataKeyStore
    {
        $factories = [];
        foreach ($clients as $name => $kms) {
            $factories[$name] = static fn (): object => $kms;
        }

        return new DataKeyStore($this->connection, new ServiceLocator($factories), $client, 'app', $secret, $table, 32, $maxAgeSeconds, $clock, $bindWrappingContext);
    }

    private function plant(string $scope, Ciphertext $wrapped, #[\SensitiveParameter] string $key): string
    {
        $reference = new UuidV7(UuidV7::generate(new \DateTimeImmutable('+1 second')))->toBinary();

        $this->connection->insert(DataKeyStore::DEFAULT_TABLE, [
            'id' => $reference,
            'scope' => $scope,
            'key_material' => $wrapped->blob,
            'master_key_id' => $wrapped->keyId,
            'client' => 'default',
            'binding' => StoredDataKey::bindingFor($reference, $scope, $key, 'not the secret of the store'),
        ], [
            'id' => ParameterType::BINARY,
            'key_material' => ParameterType::BINARY,
            'binding' => ParameterType::BINARY,
        ]);

        return $reference;
    }

    private function storeWithoutTable(string $table = DataKeyStore::DEFAULT_TABLE): DataKeyStore
    {
        $kms = new InMemoryKms();

        return new DataKeyStore($this->connection, new ServiceLocator(['default' => static fn (): object => $kms]), 'default', 'app', self::SECRET, $table);
    }

    private function age(DataKeyStore $store, string $scope, int $seconds): string
    {
        $handle = $store->current($scope);
        $backdated = Uuid::fromString(UuidV7::generate(\DateTimeImmutable::createFromTimestamp(time() - $seconds)))->toBinary();
        $binding = $handle->use(static fn (#[\SensitiveParameter] string $plaintext): string => StoredDataKey::bindingFor($backdated, $scope, $plaintext, self::SECRET));

        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET id = ?, binding = ? WHERE id = ?', DataKeyStore::DEFAULT_TABLE),
            [$backdated, $binding, $handle->reference],
            [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY],
        );

        return $backdated;
    }

    /**
     * @param list<string> $queries
     */
    private function recordQueries(array &$queries): void
    {
        $config = new Configuration();
        $config->setMiddlewares([new Middleware(new class($queries) extends AbstractLogger {
            public function __construct(private array &$queries)
            {
            }

            public function log($level, $message, array $context = []): void
            {
                if (isset($context['sql'])) {
                    $this->queries[] = $context['sql'];
                }
            }
        })]);

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
    }

    private function rowCount(): int
    {
        return (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', DataKeyStore::DEFAULT_TABLE));
    }

    private static function keyMaterialOf(Connection $connection): string
    {
        $material = $connection->fetchOne(\sprintf('SELECT key_material FROM %s', DataKeyStore::DEFAULT_TABLE));

        return \is_resource($material) ? stream_get_contents($material) : (string) $material;
    }

    private static function plaintextOf(DataKeyHandle $handle): string
    {
        return $handle->use(static fn (string $key): string => $key);
    }
}

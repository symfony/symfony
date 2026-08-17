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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[RequiresPhpExtension('pdo_sqlite')]
class DataKeyStoreTest extends TestCase
{
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

    public function testTheTableHoldsFiveColumnsAndNothingMore()
    {
        $store = $this->store();
        $store->current('user.email');

        $row = $this->connection->fetchAssociative(\sprintf('SELECT * FROM %s', DataKeyStore::DEFAULT_TABLE));

        $this->assertSame(['id', 'scope', 'key_material', 'master_key_id', 'client'], array_keys($row));
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

    /**
     * A store that was told nothing about rotation still rotates, because the envelopes it feeds
     * are sealed under a random 96-bit IV: what they cannot survive is a key that seals payloads
     * forever. The retired row stays, so what it sealed stays readable.
     */
    public function testAStoreThatWasToldNothingRetiresAKeyOlderThanTheDefaultAge()
    {
        $store = new DataKeyStore($this->connection, new ServiceLocator(['default' => static fn (): object => new InMemoryKms()]), 'default', 'app');
        $store->createTable();

        $aged = $this->age($store->current('user.email')->reference, DataKeyStore::DEFAULT_MAX_AGE_SECONDS + 86400);
        $store->forget();

        $this->assertNotSame($aged, $store->current('user.email')->reference);
        $this->assertSame(2, $this->rowCount());
    }

    public function testAKeyYoungerThanTheDefaultAgeIsKept()
    {
        $store = $this->store();

        $kept = $this->age($store->current('user.email')->reference, DataKeyStore::DEFAULT_MAX_AGE_SECONDS - 86400);
        $store->forget();

        $this->assertSame($kept, $store->current('user.email')->reference);
        $this->assertSame(1, $this->rowCount());
    }

    /**
     * Turning rotation off is something an application can still ask for, and then owns.
     */
    public function testANullMaxAgeNeverRotates()
    {
        $store = $this->store(maxAgeSeconds: null);

        $kept = $this->age($store->current('user.email')->reference, 10 * DataKeyStore::DEFAULT_MAX_AGE_SECONDS);
        $store->forget();

        $this->assertSame($kept, $store->current('user.email')->reference);
        $this->assertSame(1, $this->rowCount());
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
        $store->current('user.email');
        $row = iterator_to_array($store->all(), false)[0];

        $this->expectException(DataKeyNotFoundException::class);
        $store->rewrap(str_repeat("\x00", 16), $row->wrapped, 'default');
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
            $store->rewrap($row->reference, $azure->encrypt('app', $plaintext), 'azure');
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
        $reference = $store->current('user.email')->reference;
        $row = iterator_to_array($store->all(), false)[0];

        $store->rewrap($reference, $row->wrapped, 'azure');
        $store->forget();

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

    public function testACustomTableNameIsHonoured()
    {
        $store = $this->store(table: 'app_deks');
        $store->current('user.email');

        $this->assertTrue($this->connection->createSchemaManager()->tablesExist(['app_deks']));
    }

    /**
     * @param array<string, object>|null $clients
     */
    private function store(?array $clients = null, string $client = 'default', string $table = DataKeyStore::DEFAULT_TABLE, ?int $maxAgeSeconds = DataKeyStore::DEFAULT_MAX_AGE_SECONDS): DataKeyStore
    {
        $factories = [];
        foreach ($clients ?? ['default' => new InMemoryKms()] as $name => $kms) {
            $factories[$name] = static fn (): object => $kms;
        }

        $store = new DataKeyStore($this->connection, new ServiceLocator($factories), $client, 'app', $table, maxAgeSeconds: $maxAgeSeconds);
        $store->createTable();

        return $store;
    }

    /**
     * Backdates a row by rewriting its reference as a UUIDv7 minted that long ago, which is where
     * the store reads the age of a key from, and returns the reference it now answers to.
     */
    private function age(string $reference, int $seconds): string
    {
        $backdated = Uuid::fromString(UuidV7::generate(new \DateTimeImmutable(\sprintf('@%d', time() - $seconds))))->toBinary();

        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET id = ? WHERE id = ?', DataKeyStore::DEFAULT_TABLE),
            [$backdated, $reference],
            [ParameterType::BINARY, ParameterType::BINARY],
        );

        return $backdated;
    }

    private function rowCount(): int
    {
        return (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', DataKeyStore::DEFAULT_TABLE));
    }

    private static function plaintextOf(DataKeyHandle $handle): string
    {
        return $handle->use(static fn (string $key): string => $key);
    }
}

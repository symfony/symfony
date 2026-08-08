<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Test;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

class InMemoryDataKeyStoreTest extends TestCase
{
    public function testTheFirstCallCreatesTheKeyOfTheScope()
    {
        $store = new InMemoryDataKeyStore();

        $this->assertInstanceOf(DataKeyHandle::class, $store->current('user.email'));
        $this->assertCount(1, iterator_to_array($store->all()));
    }

    public function testTheSameScopeKeepsTheSameKey()
    {
        $store = new InMemoryDataKeyStore();

        $this->assertSame($store->current('user.email')->reference, $store->current('user.email')->reference);
        $this->assertCount(1, iterator_to_array($store->all()), 'a scope must not accumulate keys.');
    }

    public function testTwoScopesGetTwoKeys()
    {
        $store = new InMemoryDataKeyStore();

        $this->assertNotSame($store->current('user.email')->reference, $store->current('user.phone')->reference);
        $this->assertCount(2, iterator_to_array($store->all()));
    }

    public function testTheReferenceIsATimeOrderedUuidOf16Bytes()
    {
        $reference = (new InMemoryDataKeyStore())->current('user.email')->reference;

        $this->assertSame(16, \strlen($reference), 'the reference travels inside envelopes, so it stays compact.');
        $this->assertInstanceOf(UuidV7::class, Uuid::fromBinary($reference), 'a v7 carries its creation instant, which is why the store needs no timestamp column.');
    }

    public function testGetResolvesTheKeyThatWasStored()
    {
        $store = new InMemoryDataKeyStore();
        $handle = $store->current('user.email');
        $plaintext = self::plaintextOf($handle);
        $store->forget();

        $this->assertSame($plaintext, self::plaintextOf($store->get($handle->reference)));
    }

    public function testGetOnAnUnknownReferenceThrows()
    {
        $store = new InMemoryDataKeyStore();

        $this->expectException(DataKeyNotFoundException::class);
        $this->expectExceptionMessage('Data key "6e6f7065" was not found in the store.');
        $store->get('nope');
    }

    public function testRotationRetiresTheCurrentKeyWithoutLosingIt()
    {
        $store = new InMemoryDataKeyStore();
        $retired = $store->current('user.email');
        $retiredPlaintext = self::plaintextOf($retired);

        $fresh = $store->rotate('user.email');

        $this->assertNotSame($retired->reference, $fresh->reference);
        $this->assertSame($fresh->reference, $store->current('user.email')->reference, 'the fresh key becomes current.');
        $this->assertSame($retiredPlaintext, self::plaintextOf($store->get($retired->reference)), 'payloads written before the rotation must still resolve.');
    }

    public function testAZeroMaxAgeRotatesOnEveryCall()
    {
        $store = new InMemoryDataKeyStore(maxAgeSeconds: 0);

        $this->assertNotSame($store->current('user.email')->reference, $store->current('user.email')->reference);
    }

    public function testAnUnreachedMaxAgeDoesNotRotate()
    {
        $store = new InMemoryDataKeyStore(maxAgeSeconds: 3600);

        $this->assertSame($store->current('user.email')->reference, $store->current('user.email')->reference);
    }

    public function testAllListsInCreationOrder()
    {
        $store = new InMemoryDataKeyStore();
        $first = $store->current('a')->reference;
        $second = $store->current('b')->reference;

        $this->assertSame([$first, $second], array_map(static fn (StoredDataKey $row): string => $row->reference, iterator_to_array($store->all())));
    }

    public function testAllFiltersByClient()
    {
        $store = new InMemoryDataKeyStore(client: 'aws');
        $store->current('user.email');

        $this->assertCount(1, iterator_to_array($store->all('aws')));
        $this->assertCount(0, iterator_to_array($store->all('azure')));
    }

    public function testRewrapKeepsTheReferenceAndTheScope()
    {
        $store = new InMemoryDataKeyStore();
        $reference = $store->current('user.email')->reference;
        $wrapped = self::rowOf($store)->wrapped;

        $store->rewrap($reference, $wrapped, 'azure');

        $rewrapped = self::rowOf($store);
        $this->assertSame($reference, $rewrapped->reference);
        $this->assertSame('user.email', $rewrapped->scope);
        $this->assertSame('azure', $rewrapped->client);
    }

    public function testRewrapOnAnUnknownReferenceThrows()
    {
        $store = new InMemoryDataKeyStore();
        $store->current('user.email');

        $this->expectException(DataKeyNotFoundException::class);
        $store->rewrap('nope', self::rowOf($store)->wrapped, 'default');
    }

    public function testAMissingClientIsReportedLoudly()
    {
        $store = new InMemoryDataKeyStore(['aws' => new OpenSslKms(new InMemoryKeyLoader(['app' => str_repeat('k', 32)]))], 'typo');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No KMS client named "typo" was given to the store; available: "aws".');
        $store->current('user.email');
    }

    /**
     * The migration scenario: both providers configured at once, each row unwrapped by the client it
     * records. Two distinct master keys make the assertion discriminating, unwrapping with the wrong
     * one failing instead of silently succeeding.
     */
    #[RequiresPhpExtension('openssl')]
    public function testARewrappedRowUnwrapsThroughItsNewClient()
    {
        $azure = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $store = self::migratingStore($azure);
        $plaintext = self::plaintextOf($store->current('user.email'));
        $row = self::rowOf($store);

        $store->rewrap($row->reference, $azure->encrypt('app', $plaintext), 'azure');
        $store->forget();

        $this->assertSame($plaintext, self::plaintextOf($store->get($row->reference)));
    }

    #[RequiresPhpExtension('openssl')]
    public function testARowClaimingTheWrongClientFailsToUnwrap()
    {
        $store = self::migratingStore(new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)])));
        $store->current('user.email');
        $row = self::rowOf($store);

        $store->rewrap($row->reference, $row->wrapped, 'azure');
        $store->forget();

        $this->expectException(DecryptionFailedException::class);
        $store->get($row->reference);
    }

    private static function migratingStore(OpenSslKms $azure): InMemoryDataKeyStore
    {
        return new InMemoryDataKeyStore([
            'aws' => new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)])),
            'azure' => $azure,
        ], 'aws');
    }

    private static function rowOf(InMemoryDataKeyStore $store): StoredDataKey
    {
        return iterator_to_array($store->all())[0];
    }

    private static function plaintextOf(DataKeyHandle $handle): string
    {
        return $handle->use(static fn (string $key): string => $key);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\StoredFormat;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;

#[RequiresPhpExtension('openssl')]
class StoredEnvelopeEncrypterTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private InMemoryDataKeyStore $store;
    private StoredEnvelopeEncrypter $encrypter;

    protected function setUp(): void
    {
        $this->store = new InMemoryDataKeyStore();
        $this->encrypter = new StoredEnvelopeEncrypter($this->store);
    }

    public function testRoundTrip()
    {
        $envelope = $this->encrypter->encrypt('user.email', 'jane@example.com');

        $this->assertSame('jane@example.com', $this->encrypter->decrypt($envelope));
    }

    public function testTheEnvelopeIsWrittenInTheStoredFormat()
    {
        $envelope = $this->encrypter->encrypt('user.email', 'hello');

        $this->assertInstanceOf(StoredFormat::class, $envelope->format);
        $this->assertNull($envelope->wrappedDek, 'the wrapped key stays in the store, not in the payload.');
        $this->assertSame($this->store->current('user.email')->reference, $envelope->reference);
    }

    public function testAllPayloadsOfAScopeShareOneDataKey()
    {
        $first = $this->encrypter->encrypt('user.email', 'one');
        $second = $this->encrypter->encrypt('user.email', 'two');

        $this->assertSame($first->reference, $second->reference);
        $this->assertCount(1, iterator_to_array($this->store->all()), 'sharing the key is what saves the KMS round trips.');
    }

    public function testTwoScopesDoNotShareTheirDataKey()
    {
        $email = $this->encrypter->encrypt('user.email', 'hello');
        $phone = $this->encrypter->encrypt('user.phone', 'hello');

        $this->assertNotSame($email->reference, $phone->reference);
    }

    public function testAPayloadWrittenBeforeARotationStillDecrypts()
    {
        $envelope = $this->encrypter->encrypt('user.email', 'written before');
        $this->store->rotate('user.email');

        $this->assertSame('written before', $this->encrypter->decrypt($envelope));
        $this->assertNotSame($envelope->reference, $this->encrypter->encrypt('user.email', 'after')->reference);
    }

    public function testAnEnvelopeSurvivesItsOwnFraming()
    {
        $envelope = $this->encrypter->encrypt('user.email', 'through the wire');

        $this->assertSame('through the wire', $this->encrypter->decrypt(Envelope::fromBytes((string) $envelope)));
    }

    public function testTheAadMustMatch()
    {
        $envelope = $this->encrypter->encrypt('user.email', 'hello', 'tenant=acme');

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter->decrypt($envelope, 'tenant=globex');
    }

    public function testTheSameDataKeyServesTwoDifferentAads()
    {
        $acme = $this->encrypter->encrypt('user.email', 'hello', 'tenant=acme');
        $globex = $this->encrypter->encrypt('user.email', 'hello', 'tenant=globex');

        $this->assertSame($acme->reference, $globex->reference, 'the AAD binds the payload, never the shared data key.');
        $this->assertSame('hello', $this->encrypter->decrypt($acme, 'tenant=acme'));
        $this->assertSame('hello', $this->encrypter->decrypt($globex, 'tenant=globex'));
    }

    public function testALostDataKeyIsReportedAsSuch()
    {
        $envelope = $this->encrypter->encrypt('user.email', 'hello');
        $orphan = Envelope::referencing(str_repeat("\x00", 16), $envelope->iv, $envelope->tag, $envelope->ciphertext);

        $this->expectException(DataKeyNotFoundException::class);
        $this->encrypter->decrypt($orphan);
    }

    public function testATamperedCiphertextFails()
    {
        $envelope = $this->encrypter->encrypt('user.email', 'hello world');
        $tampered = Envelope::referencing(
            $envelope->reference,
            $envelope->iv,
            $envelope->tag,
            substr_replace($envelope->ciphertext, $envelope->ciphertext[-1] ^ "\x01", -1, 1),
        );

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter->decrypt($tampered);
    }

    /**
     * The data key is handed to a closure, which is a function like any other: its argument lands in
     * the trace of anything the local AEAD raises.
     */
    public function testTheDataKeyDoesNotReachStackTraces()
    {
        $envelope = $this->encrypter->encrypt('user.email', 'jane@example.com');
        $dataKey = $this->store->get($envelope->reference)->use(static fn (string $key): string => $key);
        $tampered = Envelope::referencing(
            $envelope->reference,
            $envelope->iv,
            $envelope->tag,
            substr_replace($envelope->ciphertext, $envelope->ciphertext[-1] ^ "\x01", -1, 1),
        );

        $trace = self::traceOf(fn () => $this->encrypter->decrypt($tampered));

        self::assertRedacted($dataKey, $trace);
    }

    public function testASelfContainedEnvelopeIsRefusedWithoutAFallback()
    {
        $legacy = (new EnvelopeEncrypter(new InMemoryKms()))->encrypt('app', 'hello');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('carries its own data key');
        $this->encrypter->decrypt($legacy);
    }

    public function testTheMigrationReadsBothFormatsAndWritesTheStoredOne()
    {
        $kms = new InMemoryKms();
        $legacy = new EnvelopeEncrypter($kms);
        $migrating = new StoredEnvelopeEncrypter($this->store, $legacy);

        $before = $legacy->encrypt('app', 'written before the store');
        $after = $migrating->encrypt('user.email', 'written after the store');

        $this->assertSame('written before the store', $migrating->decrypt($before));
        $this->assertSame('written after the store', $migrating->decrypt($after));
        $this->assertInstanceOf(StoredFormat::class, $after->format, 'new payloads move to the stored format.');
    }
}

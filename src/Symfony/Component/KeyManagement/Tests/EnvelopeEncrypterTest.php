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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\SelfContainedFormat;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;

#[RequiresPhpExtension('openssl')]
class EnvelopeEncrypterTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private InMemoryKms $kms;
    private EnvelopeEncrypter $encrypter;

    protected function setUp(): void
    {
        $this->kms = new InMemoryKms();
        $this->encrypter = new EnvelopeEncrypter($this->kms);
    }

    public static function providePayloads(): iterable
    {
        yield 'empty' => [''];
        yield 'small text' => ['hello'];
        yield 'unicode' => ['héllo café 日本語'];
        yield 'binary' => ["\x00\xFF\x01\x02\x03\xFE"];
        yield 'long' => [str_repeat('lorem ipsum ', 4096)];
    }

    #[DataProvider('providePayloads')]
    public function testRoundTrip(string $payload)
    {
        $envelope = $this->encrypter->encrypt('app-key', $payload);

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertSame($payload, $this->encrypter->decrypt($envelope));
    }

    public function testRoundTripIsNotDeterministic()
    {
        $a = $this->encrypter->encrypt('app-key', 'hello');
        $b = $this->encrypter->encrypt('app-key', 'hello');

        $this->assertNotSame((string) $a, (string) $b, 'Each call must use a fresh DEK and IV.');
    }

    public function testEnvelopeRoundTripsThroughItsByteFraming()
    {
        $original = $this->encrypter->encrypt('app-key', 'hello');

        $reparsed = Envelope::fromBytes((string) $original);

        $this->assertSame($original->keyId, $reparsed->keyId);
        $this->assertSame($original->wrappedDek, $reparsed->wrappedDek);
        $this->assertSame($original->format->id(), $reparsed->format->id());
        $this->assertSame($original->iv, $reparsed->iv);
        $this->assertSame($original->tag, $reparsed->tag);
        $this->assertSame($original->ciphertext, $reparsed->ciphertext);
        $this->assertSame('hello', $this->encrypter->decrypt($reparsed));
    }

    public function testTheEnvelopeIsWrittenInTheSelfContainedFormat()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');

        $this->assertSame(SelfContainedFormat::ID, $envelope->format->id());
        $this->assertNotNull($envelope->wrappedDek, 'the wrapped key travels with the payload, which is what makes it self-sufficient.');
    }

    public function testAadRoundTrip()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello', 'tenant=acme');

        $this->assertSame('hello', $this->encrypter->decrypt($envelope, 'tenant=acme'));
    }

    public function testWrongAadFails()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello', 'tenant=acme');

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter->decrypt($envelope, 'tenant=globex');
    }

    public function testEnvelopeIsPortableAcrossEncrypterInstances()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');

        $other = new EnvelopeEncrypter($this->kms);

        $this->assertSame('hello', $other->decrypt($envelope));
    }

    public function testTamperedCiphertextFails()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello world');
        $tampered = Envelope::selfContained(
            $envelope->keyId,
            $envelope->wrappedDek,
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
        $envelope = $this->encrypter->encrypt('app-key', 'hello world');
        $dataKey = $this->kms->decrypt(new Ciphertext($envelope->wrappedDek, $envelope->keyId));
        $tampered = Envelope::selfContained(
            $envelope->keyId,
            $envelope->wrappedDek,
            $envelope->iv,
            $envelope->tag,
            substr_replace($envelope->ciphertext, $envelope->ciphertext[-1] ^ "\x01", -1, 1),
        );

        $trace = self::traceOf(fn () => $this->encrypter->decrypt($tampered));

        self::assertRedacted($dataKey, $trace);
    }

    public function testTamperedTagFails()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');
        $tampered = Envelope::selfContained(
            $envelope->keyId,
            $envelope->wrappedDek,
            $envelope->iv,
            substr_replace($envelope->tag, $envelope->tag[0] ^ "\x01", 0, 1),
            $envelope->ciphertext,
        );

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter->decrypt($tampered);
    }

    public function testEnvelopeKmsIsCalledForGenerateAndUnwrap()
    {
        $countBefore = $this->kms->calls;
        $envelope = $this->encrypter->encrypt('app-key', 'hello');
        $afterEncrypt = $this->kms->calls - $countBefore;

        $this->encrypter->decrypt($envelope);
        $afterDecrypt = $this->kms->calls - $countBefore - $afterEncrypt;

        // encrypt: generateDataKey() -> 1 call to encrypt() inside InMemoryKms.
        $this->assertSame(2, $afterEncrypt);
        // decrypt: unwrapDataKey() -> 1 call to decrypt() inside InMemoryKms.
        $this->assertSame(1, $afterDecrypt);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Local;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

/**
 * End-to-end coverage of the EnvelopeEncrypter + SodiumKms pair: every
 * crypto step uses a real primitive (libsodium for the wrapped data key,
 * OpenSSL AES-256-GCM for the bulk payload), so this exercises the wire
 * format produced by SodiumKms feeding back into Envelope::fromBytes()
 * and the unwrap path returning a DEK that AES-GCM accepts.
 */
#[RequiresPhpExtension('sodium')]
#[RequiresPhpExtension('openssl')]
class SodiumEnvelopeEncrypterTest extends TestCase
{
    private SodiumKms $kms;
    private EnvelopeEncrypter $encrypter;

    protected function setUp(): void
    {
        $this->kms = new SodiumKms(new InMemoryKeyLoader([
            'app-key' => sodium_crypto_aead_xchacha20poly1305_ietf_keygen(),
        ]));
        $this->encrypter = new EnvelopeEncrypter($this->kms);
    }

    public static function providePayloads(): iterable
    {
        yield 'empty' => [''];
        yield 'short text' => ['hello world'];
        yield 'unicode' => ['héllo café 日本語 🔐'];
        yield 'binary' => ["\x00\xFF\x01\x02\x03\xFE\nplus\nlines"];
        yield 'large' => [random_bytes(256 * 1024)];
    }

    #[DataProvider('providePayloads')]
    public function testRealCryptoRoundTrip(string $payload)
    {
        $envelope = $this->encrypter->encrypt('app-key', $payload);

        $this->assertSame($payload, $this->encrypter->decrypt($envelope));
    }

    public function testEnvelopeRoundTripsThroughByteFraming()
    {
        $payload = 'hello world';
        $bytes = (string) $this->encrypter->encrypt('app-key', $payload);

        $reparsed = Envelope::fromBytes($bytes);

        $this->assertSame('app-key', $reparsed->keyId);
        $this->assertSame($payload, $this->encrypter->decrypt($reparsed));
    }

    public function testAadIsBoundToBothLayers()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello', 'tenant=acme');

        $this->assertSame('hello', $this->encrypter->decrypt($envelope, 'tenant=acme'));

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter->decrypt($envelope, 'tenant=globex');
    }

    public function testEnvelopePortabilityAcrossEncrypterInstances()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');

        $other = new EnvelopeEncrypter($this->kms);

        $this->assertSame('hello', $other->decrypt($envelope));
    }

    public function testDecryptingUnderADifferentKeyFailsCleanly()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');

        $otherKms = new SodiumKms(new InMemoryKeyLoader([
            'app-key' => sodium_crypto_aead_xchacha20poly1305_ietf_keygen(),
        ]));
        $otherEncrypter = new EnvelopeEncrypter($otherKms);

        $this->expectException(DecryptionFailedException::class);
        $otherEncrypter->decrypt($envelope);
    }
}

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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

#[RequiresPhpExtension('sodium')]
class SodiumKmsTest extends TestCase
{
    private SodiumKms $kms;

    protected function setUp(): void
    {
        $this->kms = new SodiumKms(new InMemoryKeyLoader([
            'app' => sodium_crypto_aead_xchacha20poly1305_ietf_keygen(),
            'app-rotated' => sodium_crypto_aead_xchacha20poly1305_ietf_keygen(),
        ]));
    }

    public function testRoundTrip()
    {
        $ciphertext = $this->kms->encrypt('app', 'hello');

        $this->assertSame('app', $ciphertext->keyId);
        $this->assertNotSame('hello', $ciphertext->blob);
        $this->assertSame('hello', $this->kms->decrypt($ciphertext));
    }

    public function testDecryptFromReconstructedCiphertext()
    {
        $blob = $this->kms->encrypt('app', 'hello')->blob;

        $this->assertSame('hello', $this->kms->decrypt(new Ciphertext($blob, 'app')));
    }

    public function testCiphertextsAreNotDeterministic()
    {
        $a = $this->kms->encrypt('app', 'hello')->blob;
        $b = $this->kms->encrypt('app', 'hello')->blob;

        $this->assertNotSame($a, $b, 'A fresh nonce must be drawn for every encryption.');
    }

    public function testDeterministicModeYieldsTheSameCiphertext()
    {
        $a = $this->kms->encrypt('app', 'hello', deterministic: true)->blob;
        $b = $this->kms->encrypt('app', 'hello', deterministic: true)->blob;
        $c = $this->kms->encrypt('app', 'world', deterministic: true)->blob;

        $this->assertSame($a, $b, 'Deterministic mode must produce identical ciphertexts for the same plaintext.');
        $this->assertNotSame($a, $c, 'Different plaintexts must still produce different ciphertexts.');
    }

    public function testDeterministicCiphertextRoundTrips()
    {
        $ciphertext = $this->kms->encrypt('app', 'hello', deterministic: true);

        $this->assertSame('hello', $this->kms->decrypt($ciphertext));
    }

    public function testDeterministicModeDerivesTheNonceFromTheAadToo()
    {
        $acme = $this->kms->encrypt('app', 'hello', 'tenant=acme', true)->blob;
        $globex = $this->kms->encrypt('app', 'hello', 'tenant=globex', true)->blob;

        $this->assertNotSame(self::nonceOf($acme), self::nonceOf($globex), 'Two AADs sharing a nonce under the same key would reveal the Poly1305 one-time key.');
        $this->assertSame($acme, $this->kms->encrypt('app', 'hello', 'tenant=acme', true)->blob, 'The same (key, aad, plaintext) triple must still produce the same ciphertext.');
    }

    public function testDeterministicNonceIsIndependentOfThePlatformIntegerSize()
    {
        $kms = new SodiumKms(new InMemoryKeyLoader(['app' => str_repeat("\x01", \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)]));

        $blob = $kms->encrypt('app', 'hello', 'tenant=acme', true)->blob;

        $this->assertSame('dccc9fea8202618f5480e5a399b3704184d8d93cdfa30e68', bin2hex(self::nonceOf($blob)), 'A deterministic ciphertext outlives the process that wrote it: 32-bit and 64-bit builds must derive the same nonce.');
    }

    public function testDeterministicCiphertextWithAadRoundTrips()
    {
        $ciphertext = $this->kms->encrypt('app', 'hello', 'tenant=acme', true);

        $this->assertSame('hello', $this->kms->decrypt($ciphertext, 'tenant=acme'));
    }

    public function testDecryptionFailsOnTamperedCiphertext()
    {
        $blob = $this->kms->encrypt('app', 'hello')->blob;
        $tampered = substr_replace($blob, $blob[-1] ^ "\x01", -1, 1);

        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt(new Ciphertext($tampered, 'app'));
    }

    public function testDecryptionFailsOnWrongKey()
    {
        $ciphertext = $this->kms->encrypt('app', 'hello');

        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt(new Ciphertext($ciphertext->blob, 'app-rotated'));
    }

    public function testDecryptionFailsOnUnknownVersion()
    {
        $blob = $this->kms->encrypt('app', 'hello')->blob;

        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt(new Ciphertext("\xFF".substr($blob, 1), 'app'));
    }

    public function testDecryptionFailsOnTruncatedBlob()
    {
        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt(new Ciphertext("\x01", 'app'));
    }

    public function testAadIsEnforced()
    {
        $ciphertext = $this->kms->encrypt('app', 'hello', 'tenant=acme');

        $this->assertSame('hello', $this->kms->decrypt($ciphertext, 'tenant=acme'));

        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt($ciphertext, 'tenant=globex');
    }

    public function testAadIsForwardedAsOpaqueBytes()
    {
        // Caller must produce identical bytes on both sides; the bridge does
        // not canonicalize arrays for them.
        $ciphertext = $this->kms->encrypt('app', 'hello', "\x00\xFFopaque-bytes");

        $this->assertSame('hello', $this->kms->decrypt($ciphertext, "\x00\xFFopaque-bytes"));
    }

    public function testEncryptOnUnknownKeyThrows()
    {
        $this->expectException(KeyNotFoundException::class);
        $this->kms->encrypt('missing', 'hello');
    }

    public function testDecryptOnUnknownKeyMasksAsDecryptionFailure()
    {
        // Avoids leaking which key ids exist via a distinguishable exception.
        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt(new Ciphertext('whatever', 'missing'));
    }

    public function testInvalidKeyMaterialIsRejectedOnFirstUse()
    {
        $kms = new SodiumKms(new InMemoryKeyLoader(['short' => 'too-short']));

        $this->expectException(InvalidArgumentException::class);
        $kms->encrypt('short', 'hello');
    }

    public function testGenerateDataKeyExposesPlaintextOnceAndWrapsItUnderTheMasterKey()
    {
        $dataKey = $this->kms->generateDataKey('app', 32);
        $this->assertInstanceOf(Ciphertext::class, $dataKey->wrapped);

        $captured = $dataKey->use(static fn (string $plaintext): string => $plaintext);
        $this->assertSame(32, \strlen($captured));
        $this->assertSame($captured, $this->kms->decrypt($dataKey->wrapped));
        $this->assertTrue($dataKey->isConsumed());
    }

    public function testDataKeyCannotBeUsedTwice()
    {
        $dataKey = $this->kms->generateDataKey('app', 32);
        $dataKey->use(static fn (string $plaintext): string => $plaintext);

        $this->expectException(\Symfony\Component\KeyManagement\Exception\LogicException::class);
        $dataKey->use(static fn (string $plaintext): string => $plaintext);
    }

    public function testDataKeyIsWipedOnExceptionInsideUse()
    {
        $dataKey = $this->kms->generateDataKey('app', 32);

        try {
            $dataKey->use(static fn () => throw new \RuntimeException('boom'));
            $this->fail('Exception should have bubbled out of use().');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($dataKey->isConsumed());
    }

    public function testGenerateDataKeyRejectsTooShortLength()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->kms->generateDataKey('app', 8);
    }

    public function testUnwrapDataKeyRoundTripsTheDek()
    {
        $original = $this->kms->generateDataKey('app', 32);
        $captured = $original->use(static fn (string $p): string => $p);

        $recovered = $this->kms->unwrapDataKey($original->wrapped);
        $this->assertSame($captured, $recovered->use(static fn (string $p): string => $p));
    }

    public function testUnwrapDataKeyOnTamperedCiphertextFails()
    {
        $wrapped = $this->kms->generateDataKey('app', 32)->wrapped;
        $tampered = new Ciphertext(substr_replace($wrapped->blob, $wrapped->blob[-1] ^ "\x01", -1, 1), 'app');

        $this->expectException(DecryptionFailedException::class);
        $this->kms->unwrapDataKey($tampered);
    }

    /**
     * Reads the nonce out of the `[version][nonce][ciphertext||tag]` blob layout.
     */
    private static function nonceOf(string $blob): string
    {
        return substr($blob, 1, \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    }
}

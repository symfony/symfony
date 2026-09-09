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
use Symfony\Component\KeyManagement\Local\OpenSslKms;

#[RequiresPhpExtension('openssl')]
class OpenSslKmsTest extends TestCase
{
    private OpenSslKms $kms;

    protected function setUp(): void
    {
        $this->kms = new OpenSslKms(new InMemoryKeyLoader([
            'app' => random_bytes(32),
            'app-rotated' => random_bytes(32),
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

        $this->assertNotSame($a, $b, 'A fresh IV must be drawn for every encryption.');
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

    public function testDeterministicModeDerivesTheIvFromTheAadToo()
    {
        $acme = $this->kms->encrypt('app', 'hello', 'tenant=acme', true)->blob;
        $globex = $this->kms->encrypt('app', 'hello', 'tenant=globex', true)->blob;

        $this->assertNotSame(self::ivOf($acme), self::ivOf($globex), 'Two AADs sharing an IV under the same key would reveal the GCM authentication subkey.');
        $this->assertSame($acme, $this->kms->encrypt('app', 'hello', 'tenant=acme', true)->blob, 'The same (key, aad, plaintext) triple must still produce the same ciphertext.');
    }

    public function testDeterministicIvIsIndependentOfThePlatformIntegerSize()
    {
        $kms = new OpenSslKms(new InMemoryKeyLoader(['app' => str_repeat("\x01", 32)]));

        $blob = $kms->encrypt('app', 'hello', 'tenant=acme', true)->blob;

        $this->assertSame('f2dc10a519d73227c656f30a', bin2hex(self::ivOf($blob)), 'A deterministic ciphertext outlives the process that wrote it: 32-bit and 64-bit builds must derive the same IV.');
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
        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt(new Ciphertext('whatever', 'missing'));
    }

    public function testInvalidKeyMaterialIsRejectedOnFirstUse()
    {
        $kms = new OpenSslKms(new InMemoryKeyLoader(['short' => 'too-short']));

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
     * Reads the IV out of the `[version][iv][tag][ciphertext]` blob layout.
     */
    private static function ivOf(string $blob): string
    {
        return substr($blob, 1, 12);
    }
}

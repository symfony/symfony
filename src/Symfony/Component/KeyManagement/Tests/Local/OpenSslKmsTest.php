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

    #[DataProvider('provideLegacyDeterministicCiphertexts')]
    public function testDecryptLegacyDeterministicCiphertext(string $plaintext, string $aad, string $blob)
    {
        $kms = new OpenSslKms(new InMemoryKeyLoader(['app' => str_repeat("\x01", 32)]));

        $this->assertSame($plaintext, $kms->decrypt(new Ciphertext(hex2bin($blob), 'app'), $aad));
    }

    #[DataProvider('provideLegacyDeterministicCiphertexts')]
    public function testCannotDecryptLegacyDeterministicCiphertextWithWrongAad(string $plaintext, string $aad, string $blob)
    {
        $kms = new OpenSslKms(new InMemoryKeyLoader(['app' => str_repeat("\x01", 32)]));

        $this->expectException(DecryptionFailedException::class);

        $kms->decrypt(new Ciphertext(hex2bin($blob), 'app'), $aad.'-changed');
    }

    public static function provideLegacyDeterministicCiphertexts(): iterable
    {
        yield 'plain' => ['hello', '', '013db53c318d437d074bb8d7ae1296e7753517b22de1f3ffd370559c0dd7cd353152'];
        yield 'aad' => ['hello', 'tenant=acme', '012c8a4c867d43aefe06ca3a9d56218d66ff0dd5b23e36350655c78baec2aee589ed'];
        yield 'empty' => ['', '', '01a39aab0505f01a295ecde7d73385f257a840d7af67ad8e6f0300a537'];
        yield 'binary' => ["\x00\xff", "opaque\x00", '01f9708e81f934d57cc793fea5abd5abf3a2205ddb696fbff1f7f811306138'];
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
}

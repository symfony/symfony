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

    #[DataProvider('provideLegacyDeterministicCiphertexts')]
    public function testDecryptLegacyDeterministicCiphertext(string $plaintext, string $aad, string $blob)
    {
        $kms = new SodiumKms(new InMemoryKeyLoader(['app' => str_repeat("\x01", 32)]));

        $this->assertSame($plaintext, $kms->decrypt(new Ciphertext(hex2bin($blob), 'app'), $aad));
    }

    #[DataProvider('provideLegacyDeterministicCiphertexts')]
    public function testCannotDecryptLegacyDeterministicCiphertextWithWrongAad(string $plaintext, string $aad, string $blob)
    {
        $kms = new SodiumKms(new InMemoryKeyLoader(['app' => str_repeat("\x01", 32)]));

        $this->expectException(DecryptionFailedException::class);

        $kms->decrypt(new Ciphertext(hex2bin($blob), 'app'), $aad.'-changed');
    }

    public static function provideLegacyDeterministicCiphertexts(): iterable
    {
        yield 'plain' => ['hello', '', '018568c316378698b4ee6314639f7cbae4f5d56b7402129fbb74ea486da99b725b44cb1bab674bdc9bb5af03e8fe'];
        yield 'aad' => ['hello', 'tenant=acme', '01958c327ef07fa86ba0c8cd434ac93e29a2a9d6312665c20fcacfe94ade494165b1f7e7a778e73cd5d0ef2730fc'];
        yield 'empty' => ['', '', '01d27fd4fc42f8666e34313a20e8fdad2120584440b1bfa87db484633604d888555f9782246929da0c'];
        yield 'binary' => ["\x00\xff", "opaque\x00", '01082b347fcd793ab98e9085c0b413d1e354011d6defe34ccef58a93b9c0d6302ce4ed97f2b91a7c514294'];
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

    public function testAWrappedDataKeyIsUnwrappedUnderTheSameAad()
    {
        $wrapped = $this->kms->wrapDataKey('app', str_repeat("\xAA", 32), 'tenant=acme');

        $this->assertSame(str_repeat("\xAA", 32), $this->kms->unwrapDataKey($wrapped, 'tenant=acme')->use(static fn (string $p): string => $p));

        $this->expectException(DecryptionFailedException::class);
        $this->kms->unwrapDataKey($wrapped, 'tenant=globex');
    }

    public function testUnwrapDataKeyOnTamperedCiphertextFails()
    {
        $wrapped = $this->kms->generateDataKey('app', 32)->wrapped;
        $tampered = new Ciphertext(substr_replace($wrapped->blob, $wrapped->blob[-1] ^ "\x01", -1, 1), 'app');

        $this->expectException(DecryptionFailedException::class);
        $this->kms->unwrapDataKey($tampered);
    }
}

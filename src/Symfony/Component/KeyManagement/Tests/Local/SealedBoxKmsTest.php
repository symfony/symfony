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
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SealedBoxKms;

#[RequiresPhpExtension('sodium')]
class SealedBoxKmsTest extends TestCase
{
    private string $keypair;
    private string $publicKey;

    protected function setUp(): void
    {
        $this->keypair = sodium_crypto_box_keypair();
        $this->publicKey = sodium_crypto_box_publickey($this->keypair);
    }

    public function testRoundTripWithKeypair()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));

        $ciphertext = $kms->encrypt('app', 'hello');

        $this->assertNotSame('hello', $ciphertext->blob);
        $this->assertSame('hello', $kms->decrypt($ciphertext));
    }

    public function testEncryptOnlyDeploymentCanEncryptWithPublicKeyOnly()
    {
        $writer = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->publicKey]));
        $reader = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));

        $ciphertext = $writer->encrypt('app', 'committed-secret');

        // Reader (with full keypair) decrypts what the writer (public-only) produced.
        $this->assertSame('committed-secret', $reader->decrypt($ciphertext));
    }

    public function testDecryptingWithPublicKeyOnlyFailsWithoutLeakingTheReason()
    {
        $reader = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));
        $writer = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->publicKey]));

        $ciphertext = $reader->encrypt('app', 'hello');

        // Writer-only deployment cannot decrypt.
        $this->expectException(DecryptionFailedException::class);
        $writer->decrypt($ciphertext);
    }

    public function testCiphertextsAreNotDeterministic()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));

        $a = $kms->encrypt('app', 'hello')->blob;
        $b = $kms->encrypt('app', 'hello')->blob;

        $this->assertNotSame($a, $b);
    }

    public function testTamperedCiphertextFails()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));
        $blob = $kms->encrypt('app', 'hello')->blob;
        $tampered = substr_replace($blob, $blob[-1] ^ "\x01", -1, 1);

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext($tampered, 'app'));
    }

    public function testInvalidKeyMaterialIsRejectedAtFirstUse()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader(['app' => 'too-short']));

        $this->expectException(InvalidArgumentException::class);
        $kms->encrypt('app', 'hello');
    }

    public function testEncryptOnUnknownKeyThrows()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader([]));

        $this->expectException(\Symfony\Component\KeyManagement\Exception\KeyNotFoundException::class);
        $kms->encrypt('missing', 'hello');
    }

    public function testDecryptOnUnknownKeyMasksAsDecryptionFailure()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader([]));

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext('whatever', 'missing'));
    }

    public function testNonEmptyAadIsRejectedOnEncrypt()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));

        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt('app', 'hello', 'tenant=acme');
    }

    public function testNonEmptyAadIsRejectedOnDecrypt()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));

        $this->expectException(UnsupportedOperationException::class);
        $kms->decrypt(new Ciphertext('whatever', 'app'), 'tenant=acme');
    }

    public function testDeterministicModeIsRejected()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));

        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt('app', 'hello', deterministic: true);
    }

    public function testDataKeyRoundTrip()
    {
        $kms = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->keypair]));

        $dataKey = $kms->generateDataKey('app', 32);
        $captured = $dataKey->use(static fn (string $p): string => $p);

        $this->assertSame(32, \strlen($captured));
        $this->assertSame($captured, $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $p): string => $p));
    }

    public function testDataKeyGenerationOnEncryptOnlyDeploymentWorks()
    {
        // The classic envelope-encryption write-only pattern: anyone with the
        // public key can wrap a fresh DEK; only the keypair holder can unwrap.
        $writer = new SealedBoxKms(new InMemoryKeyLoader(['app' => $this->publicKey]));

        $dataKey = $writer->generateDataKey('app', 32);

        $this->assertSame(32, \strlen($dataKey->use(static fn (string $p): string => $p)));
    }
}

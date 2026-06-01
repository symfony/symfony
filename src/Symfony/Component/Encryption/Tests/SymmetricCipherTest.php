<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Encoding;
use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslSymmetricEngine;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Envelope\Envelope;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Key\SecretKey;
use Symfony\Component\Encryption\SymmetricCipher;

final class SymmetricCipherTest extends TestCase
{
    public function testGenerateKeyReturnsSecretKey()
    {
        self::assertInstanceOf(SecretKey::class, (new SymmetricCipher())->generateKey());
    }

    public function testEncryptDecryptRoundTrip()
    {
        $cipher = new SymmetricCipher();
        $key = $cipher->generateKey();

        $ciphertext = $cipher->encrypt('attack at dawn', $key);

        self::assertNotSame('attack at dawn', $ciphertext);
        self::assertSame('attack at dawn', $cipher->decrypt($ciphertext, $key));
    }

    public function testEmptyPlaintextRoundTrips()
    {
        $cipher = new SymmetricCipher();
        $key = $cipher->generateKey();

        self::assertSame('', $cipher->decrypt($cipher->encrypt('', $key), $key));
    }

    public function testEachEncryptionUsesAFreshNonce()
    {
        $cipher = new SymmetricCipher();
        $key = $cipher->generateKey();

        self::assertNotSame($cipher->encrypt('same', $key), $cipher->encrypt('same', $key));
    }

    public function testWrongKeyFailsToDecrypt()
    {
        $cipher = new SymmetricCipher();
        $ciphertext = $cipher->encrypt('secret', $cipher->generateKey());

        $this->expectException(DecryptionException::class);

        $cipher->decrypt($ciphertext, $cipher->generateKey());
    }

    public function testTamperedCiphertextFailsToDecrypt()
    {
        $cipher = new SymmetricCipher();
        $key = $cipher->generateKey();
        $ciphertext = $cipher->encrypt('secret', $key);
        $ciphertext[\strlen($ciphertext) - 2] = 'A' === $ciphertext[\strlen($ciphertext) - 2] ? 'B' : 'A';

        $this->expectException(DecryptionException::class);

        $cipher->decrypt($ciphertext, $key);
    }

    public function testGarbageInputFailsToDecrypt()
    {
        $this->expectException(DecryptionException::class);

        (new SymmetricCipher())->decrypt('not a real ciphertext', SecretKey::generate());
    }

    public function testDecryptRejectsPasswordCiphertext()
    {
        $cipher = new SymmetricCipher();
        $ciphertext = $cipher->encryptWithPassword('secret', 'hunter2');

        $this->expectException(DecryptionException::class);

        $cipher->decrypt($ciphertext, $cipher->generateKey());
    }

    public function testDecryptWithPasswordRejectsKeyCiphertext()
    {
        $cipher = new SymmetricCipher();
        $key = $cipher->generateKey();
        $ciphertext = $cipher->encrypt('secret', $key);

        $this->expectException(DecryptionException::class);

        $cipher->decryptWithPassword($ciphertext, 'hunter2');
    }

    public function testPasswordRoundTrip()
    {
        $cipher = new SymmetricCipher();

        $ciphertext = $cipher->encryptWithPassword('attack at dawn', 'correct horse battery staple');

        self::assertSame('attack at dawn', $cipher->decryptWithPassword($ciphertext, 'correct horse battery staple'));
    }

    public function testWrongPasswordFails()
    {
        $cipher = new SymmetricCipher();
        $ciphertext = $cipher->encryptWithPassword('secret', 'right password');

        $this->expectException(DecryptionException::class);

        $cipher->decryptWithPassword($ciphertext, 'wrong password');
    }

    public function testPasswordRoundTripViaOpenSslOnlySelector()
    {
        $engine = new OpenSslSymmetricEngine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('OpenSSL engine required.');
        }
        $cipher = new SymmetricCipher(new EngineSelector([$engine]));

        $ciphertext = $cipher->encryptWithPassword('msg', 'pw');

        self::assertSame('msg', $cipher->decryptWithPassword($ciphertext, 'pw'));
    }

    public function testExportedKeyCanDecryptLater()
    {
        $cipher = new SymmetricCipher();
        $key = $cipher->generateKey();
        $ciphertext = $cipher->encrypt('persisted', $key);

        $reloaded = SecretKey::import($key->export());

        self::assertSame('persisted', $cipher->decrypt($ciphertext, $reloaded));
    }

    /**
     * The KDF cost parameters are read back from the ciphertext, so a crafted
     * envelope must not be able to force an arbitrarily expensive derivation.
     * These envelopes are rejected by the bound check before any KDF runs, so
     * the tests stay cheap.
     */
    public function testDecryptWithPasswordRejectsOversizedArgon2MemoryLimit()
    {
        $cipher = new SymmetricCipher();
        $ciphertext = $this->craftArgon2idCiphertext(2, 8 * 1024 * 1024 * 1024); // 8 GiB

        $this->expectException(DecryptionException::class);
        $this->expectExceptionMessage('key-derivation parameters are out of range');
        $cipher->decryptWithPassword($ciphertext, 'passphrase');
    }

    public function testDecryptWithPasswordRejectsExcessiveArgon2OpsLimit()
    {
        $cipher = new SymmetricCipher();
        $ciphertext = $this->craftArgon2idCiphertext(1000000, 64 * 1024 * 1024); // 1M ops

        $this->expectException(DecryptionException::class);
        $this->expectExceptionMessage('key-derivation parameters are out of range');
        $cipher->decryptWithPassword($ciphertext, 'passphrase');
    }

    public function testDecryptWithPasswordRejectsExcessivePbkdf2Iterations()
    {
        $cipher = new SymmetricCipher();
        $envelope = Envelope::forPbkdf2(
            str_repeat("\x00", Envelope::SALT_BYTES),
            4000000000, // 4 billion, far above the accepted ceiling
            str_repeat("\x00", SymmetricEngineInterface::NONCE_BYTES),
            str_repeat("\x00", SymmetricEngineInterface::TAG_BYTES),
        );

        $this->expectException(DecryptionException::class);
        $this->expectExceptionMessage('key-derivation parameters are out of range');
        $cipher->decryptWithPassword(Encoding::toBase64($envelope->serialize()), 'passphrase');
    }

    private function craftArgon2idCiphertext(int $opsLimit, int $memLimit): string
    {
        $envelope = Envelope::forArgon2id(
            str_repeat("\x00", Envelope::SALT_BYTES),
            $opsLimit,
            $memLimit,
            str_repeat("\x00", SymmetricEngineInterface::NONCE_BYTES),
            str_repeat("\x00", SymmetricEngineInterface::TAG_BYTES),
        );

        return Encoding::toBase64($envelope->serialize());
    }
}

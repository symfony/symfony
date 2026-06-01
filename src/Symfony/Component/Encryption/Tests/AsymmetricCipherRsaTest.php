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
use Symfony\Component\Encryption\AsymmetricCipher;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Key\KeyPair;

final class AsymmetricCipherRsaTest extends TestCase
{
    public function testGenerateRsaKeyPair()
    {
        $pair = (new AsymmetricCipher())->generateKeyPair('rsa', 2048);

        self::assertInstanceOf(KeyPair::class, $pair);
        self::assertSame('rsa', $pair->algorithm());
        self::assertStringContainsString('PUBLIC KEY', $pair->public()->bytes());
    }

    public function testRsaAnonymousRoundTrip()
    {
        $cipher = new AsymmetricCipher();
        $recipient = $cipher->generateKeyPair('rsa', 2048);
        $message = 'a longer message than RSA could ever encrypt directly '.str_repeat('x', 500);

        $ciphertext = $cipher->encryptAnonymous($message, $recipient->public());

        self::assertSame($message, $cipher->decryptAnonymous($ciphertext, $recipient));
    }

    public function testRsaEmptyPlaintextRoundTrips()
    {
        $cipher = new AsymmetricCipher();
        $recipient = $cipher->generateKeyPair('rsa', 2048);

        self::assertSame('', $cipher->decryptAnonymous($cipher->encryptAnonymous('', $recipient->public()), $recipient));
    }

    public function testRsaWrongRecipientFails()
    {
        $cipher = new AsymmetricCipher();
        $ciphertext = $cipher->encryptAnonymous('secret', $cipher->generateKeyPair('rsa', 2048)->public());

        $this->expectException(DecryptionException::class);

        $cipher->decryptAnonymous($ciphertext, $cipher->generateKeyPair('rsa', 2048));
    }

    public function testRsaTamperedCiphertextFails()
    {
        $cipher = new AsymmetricCipher();
        $recipient = $cipher->generateKeyPair('rsa', 2048);
        $ciphertext = $cipher->encryptAnonymous('secret', $recipient->public());
        $ciphertext[\strlen($ciphertext) - 2] = $ciphertext[\strlen($ciphertext) - 2] === 'A' ? 'B' : 'A';

        $this->expectException(DecryptionException::class);

        $cipher->decryptAnonymous($ciphertext, $recipient);
    }

    public function testRsaRejectsAuthenticatedEncryption()
    {
        $cipher = new AsymmetricCipher();
        $sender = $cipher->generateKeyPair('rsa', 2048);
        $recipient = $cipher->generateKeyPair('rsa', 2048);

        $this->expectException(InvalidKeyException::class);

        $cipher->encryptAuthenticated('secret', $sender->private(), $recipient->public());
    }

    public function testExportedRsaKeyDecryptsLater()
    {
        $cipher = new AsymmetricCipher();
        $recipient = $cipher->generateKeyPair('rsa', 2048);
        $ciphertext = $cipher->encryptAnonymous('persisted', $recipient->public());

        $reloaded = KeyPair::import($recipient->export());

        self::assertSame('persisted', $cipher->decryptAnonymous($ciphertext, $reloaded));
    }

    public function testRsaCiphertextDoesNotDecryptWithX25519Key()
    {
        if (!\function_exists('sodium_crypto_box_seal')) {
            self::markTestSkipped('ext-sodium is required for X25519 keys.');
        }

        $cipher = new AsymmetricCipher();
        $rsaRecipient = $cipher->generateKeyPair('rsa', 2048);
        $x25519Recipient = $cipher->generateKeyPair();
        $ciphertext = $cipher->encryptAnonymous('secret', $rsaRecipient->public());

        $this->expectException(DecryptionException::class);

        $cipher->decryptAnonymous($ciphertext, $x25519Recipient);
    }

    public function testX25519CiphertextDoesNotDecryptWithRsaKey()
    {
        if (!\function_exists('sodium_crypto_box_seal')) {
            self::markTestSkipped('ext-sodium is required for X25519 keys.');
        }

        $cipher = new AsymmetricCipher();
        $x25519Recipient = $cipher->generateKeyPair();
        $rsaRecipient = $cipher->generateKeyPair('rsa', 2048);
        $ciphertext = $cipher->encryptAnonymous('secret', $x25519Recipient->public());

        $this->expectException(DecryptionException::class);

        $cipher->decryptAnonymous($ciphertext, $rsaRecipient);
    }
}

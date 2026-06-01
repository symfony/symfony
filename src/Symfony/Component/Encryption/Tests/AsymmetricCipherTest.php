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
use Symfony\Component\Encryption\Key\KeyPair;

final class AsymmetricCipherTest extends TestCase
{
    protected function setUp()
    {
        if (!\function_exists('sodium_crypto_box_seal')) {
            self::markTestSkipped('ext-sodium is required.');
        }
    }

    public function testGenerateKeyPairIsX25519()
    {
        $pair = (new AsymmetricCipher())->generateKeyPair();

        self::assertInstanceOf(KeyPair::class, $pair);
        self::assertSame('x25519', $pair->algorithm());
        self::assertSame('encryption', $pair->public()->purpose());
    }

    public function testAnonymousRoundTrip()
    {
        $cipher = new AsymmetricCipher();
        $recipient = $cipher->generateKeyPair();

        $ciphertext = $cipher->encryptAnonymous('meet at noon', $recipient->public());

        self::assertNotSame('meet at noon', $ciphertext);
        self::assertSame('meet at noon', $cipher->decryptAnonymous($ciphertext, $recipient));
    }

    public function testAnonymousEmptyPlaintextRoundTrips()
    {
        $cipher = new AsymmetricCipher();
        $recipient = $cipher->generateKeyPair();

        self::assertSame('', $cipher->decryptAnonymous($cipher->encryptAnonymous('', $recipient->public()), $recipient));
    }

    public function testAnonymousWrongRecipientFails()
    {
        $cipher = new AsymmetricCipher();
        $ciphertext = $cipher->encryptAnonymous('secret', $cipher->generateKeyPair()->public());

        $this->expectException(DecryptionException::class);

        $cipher->decryptAnonymous($ciphertext, $cipher->generateKeyPair());
    }

    public function testAuthenticatedRoundTrip()
    {
        $cipher = new AsymmetricCipher();
        $sender = $cipher->generateKeyPair();
        $recipient = $cipher->generateKeyPair();

        $ciphertext = $cipher->encryptAuthenticated('signed note', $sender->private(), $recipient->public());

        self::assertSame(
            'signed note',
            $cipher->decryptAuthenticated($ciphertext, $recipient, $sender->public()),
        );
    }

    public function testAuthenticatedWrongSenderFails()
    {
        $cipher = new AsymmetricCipher();
        $sender = $cipher->generateKeyPair();
        $recipient = $cipher->generateKeyPair();
        $impostor = $cipher->generateKeyPair();
        $ciphertext = $cipher->encryptAuthenticated('secret', $sender->private(), $recipient->public());

        $this->expectException(DecryptionException::class);

        $cipher->decryptAuthenticated($ciphertext, $recipient, $impostor->public());
    }

    public function testGarbageFailsToDecrypt()
    {
        $cipher = new AsymmetricCipher();

        $this->expectException(DecryptionException::class);

        $cipher->decryptAnonymous('not a ciphertext', $cipher->generateKeyPair());
    }

    public function testDecryptAnonymousRejectsAuthenticatedEnvelope()
    {
        $cipher = new AsymmetricCipher();
        $recipient = $cipher->generateKeyPair();
        $authCiphertext = $cipher->encryptAuthenticated('x', $recipient->private(), $recipient->public());

        $this->expectException(DecryptionException::class);

        $cipher->decryptAnonymous($authCiphertext, $recipient);
    }

    public function testDecryptAuthenticatedRejectsAnonymousEnvelope()
    {
        $cipher = new AsymmetricCipher();
        $recipient = $cipher->generateKeyPair();
        $anonCiphertext = $cipher->encryptAnonymous('x', $recipient->public());

        $this->expectException(DecryptionException::class);

        $cipher->decryptAuthenticated($anonCiphertext, $recipient, $recipient->public());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Engine\Sodium\SodiumX25519Engine;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

final class SodiumX25519EngineTest extends TestCase
{
    private function engine(): SodiumX25519Engine
    {
        $engine = new SodiumX25519Engine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('ext-sodium is required.');
        }

        return $engine;
    }

    public function testGenerateKeyPairProduces32ByteKeys()
    {
        [$public, $private] = $this->engine()->generateKeyPair();

        self::assertSame(32, \strlen($public));
        self::assertSame(32, \strlen($private));
    }

    public function testAnonymousRoundTrip()
    {
        $engine = $this->engine();
        [$public, $private] = $engine->generateKeyPair();

        $sealed = $engine->sealAnonymous('to whom it may concern', $public);

        self::assertSame('to whom it may concern', $engine->openAnonymous($sealed, $public, $private));
    }

    public function testAnonymousRejectsWrongRecipient()
    {
        $engine = $this->engine();
        [$public] = $engine->generateKeyPair();
        [$otherPublic, $otherPrivate] = $engine->generateKeyPair();
        $sealed = $engine->sealAnonymous('secret', $public);

        $this->expectException(DecryptionException::class);

        $engine->openAnonymous($sealed, $otherPublic, $otherPrivate);
    }

    public function testAuthenticatedRoundTrip()
    {
        $engine = $this->engine();
        [$recipientPublic, $recipientPrivate] = $engine->generateKeyPair();
        [$senderPublic, $senderPrivate] = $engine->generateKeyPair();
        $nonce = random_bytes($engine->authenticatedNonceBytes());

        $ciphertext = $engine->encryptAuthenticated('signed delivery', $nonce, $senderPrivate, $recipientPublic);

        self::assertSame(
            'signed delivery',
            $engine->decryptAuthenticated($ciphertext, $nonce, $recipientPrivate, $senderPublic),
        );
    }

    public function testAuthenticatedRejectsWrongSender()
    {
        $engine = $this->engine();
        [$recipientPublic, $recipientPrivate] = $engine->generateKeyPair();
        [, $senderPrivate] = $engine->generateKeyPair();
        [$impostorPublic] = $engine->generateKeyPair();
        $nonce = random_bytes($engine->authenticatedNonceBytes());
        $ciphertext = $engine->encryptAuthenticated('secret', $nonce, $senderPrivate, $recipientPublic);

        $this->expectException(DecryptionException::class);

        $engine->decryptAuthenticated($ciphertext, $nonce, $recipientPrivate, $impostorPublic);
    }

    public function testAnonymousEmptyPlaintextRoundTrips()
    {
        $engine = $this->engine();
        [$public, $private] = $engine->generateKeyPair();

        $sealed = $engine->sealAnonymous('', $public);

        self::assertSame('', $engine->openAnonymous($sealed, $public, $private));
    }

    public function testTamperedAnonymousCiphertextIsRejected()
    {
        $engine = $this->engine();
        [$public, $private] = $engine->generateKeyPair();
        $sealed = $engine->sealAnonymous('secret message', $public);
        $sealed[0] = $sealed[0] === 'A' ? 'B' : 'A';

        $this->expectException(DecryptionException::class);

        $engine->openAnonymous($sealed, $public, $private);
    }

    public function testAuthenticatedEmptyPlaintextRoundTrips()
    {
        $engine = $this->engine();
        [$recipientPublic, $recipientPrivate] = $engine->generateKeyPair();
        [$senderPublic, $senderPrivate] = $engine->generateKeyPair();
        $nonce = random_bytes($engine->authenticatedNonceBytes());

        $ciphertext = $engine->encryptAuthenticated('', $nonce, $senderPrivate, $recipientPublic);

        self::assertSame(
            '',
            $engine->decryptAuthenticated($ciphertext, $nonce, $recipientPrivate, $senderPublic),
        );
    }

    public function testTamperedAuthenticatedCiphertextIsRejected()
    {
        $engine = $this->engine();
        [$recipientPublic, $recipientPrivate] = $engine->generateKeyPair();
        [$senderPublic, $senderPrivate] = $engine->generateKeyPair();
        $nonce = random_bytes($engine->authenticatedNonceBytes());
        $ciphertext = $engine->encryptAuthenticated('secret message', $nonce, $senderPrivate, $recipientPublic);
        $ciphertext[0] = $ciphertext[0] === 'A' ? 'B' : 'A';

        $this->expectException(DecryptionException::class);

        $engine->decryptAuthenticated($ciphertext, $nonce, $recipientPrivate, $senderPublic);
    }

    public function testRejectsWrongKeyLength()
    {
        $this->expectException(InvalidKeyException::class);

        $this->engine()->sealAnonymous('x', random_bytes(16));
    }

    public function testNameAndAlgorithm()
    {
        self::assertSame('sodium', $this->engine()->name());
        self::assertSame('x25519', $this->engine()->algorithm());
    }
}

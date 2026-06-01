<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine\Sodium;

use Symfony\Component\Encryption\Engine\AsymmetricEncryptionEngineInterface;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * X25519 public-key encryption via libsodium: sealed box (anonymous) and
 * authenticated crypto_box.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class SodiumX25519Engine implements AsymmetricEncryptionEngineInterface
{
    private const KEY_BYTES = 32;

    #[\Override]
    public function generateKeyPair(): array
    {
        $keypair = sodium_crypto_box_keypair();

        return [
            sodium_crypto_box_publickey($keypair),
            sodium_crypto_box_secretkey($keypair),
        ];
    }

    #[\Override]
    public function sealAnonymous(string $plaintext, string $recipientPublic): string
    {
        $this->assertKey($recipientPublic, 'recipient public');

        return sodium_crypto_box_seal($plaintext, $recipientPublic);
    }

    #[\Override]
    public function openAnonymous(string $ciphertext, string $recipientPublic, string $recipientPrivate): string
    {
        $this->assertKey($recipientPublic, 'recipient public');
        $this->assertKey($recipientPrivate, 'recipient private');

        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($recipientPrivate, $recipientPublic);
        $plaintext = sodium_crypto_box_seal_open($ciphertext, $keypair);
        if (false === $plaintext) {
            throw new DecryptionException('Anonymous decryption failed: wrong key or corrupted ciphertext.');
        }

        return $plaintext;
    }

    #[\Override]
    public function encryptAuthenticated(string $plaintext, string $nonce, string $senderPrivate, string $recipientPublic): string
    {
        $this->assertKey($senderPrivate, 'sender private');
        $this->assertKey($recipientPublic, 'recipient public');

        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($senderPrivate, $recipientPublic);

        return sodium_crypto_box($plaintext, $nonce, $keypair);
    }

    #[\Override]
    public function decryptAuthenticated(string $ciphertext, string $nonce, string $recipientPrivate, string $senderPublic): string
    {
        $this->assertKey($recipientPrivate, 'recipient private');
        $this->assertKey($senderPublic, 'sender public');

        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($recipientPrivate, $senderPublic);
        $plaintext = sodium_crypto_box_open($ciphertext, $nonce, $keypair);
        if (false === $plaintext) {
            throw new DecryptionException('Authenticated decryption failed: wrong key/sender or corrupted ciphertext.');
        }

        return $plaintext;
    }

    /** @return positive-int */
    #[\Override]
    public function authenticatedNonceBytes(): int
    {
        return \SODIUM_CRYPTO_BOX_NONCEBYTES;
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return \function_exists('sodium_crypto_box_seal');
    }

    #[\Override]
    public function algorithm(): string
    {
        return 'x25519';
    }

    #[\Override]
    public function name(): string
    {
        return 'sodium';
    }

    private function assertKey(string $key, string $label): void
    {
        if (self::KEY_BYTES !== \strlen($key)) {
            throw new InvalidKeyException(\sprintf('X25519 "%s" key must be %d bytes; got %d.', $label, self::KEY_BYTES, \strlen($key)));
        }
    }
}

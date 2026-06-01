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

use Symfony\Component\Encryption\Engine\AbstractSymmetricEngine;
use Symfony\Component\Encryption\Exception\DecryptionException;

/**
 * ChaCha20-Poly1305 (IETF) authenticated encryption via libsodium.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class SodiumSymmetricEngine extends AbstractSymmetricEngine
{
    #[\Override]
    public function encrypt(string $plaintext, string $key, string $nonce): string
    {
        $this->assertKeyAndNonce($key, $nonce);

        return sodium_crypto_aead_chacha20poly1305_ietf_encrypt($plaintext, '', $nonce, $key);
    }

    #[\Override]
    public function decrypt(string $ciphertext, string $key, string $nonce): string
    {
        $this->assertKeyAndNonce($key, $nonce);

        if (\strlen($ciphertext) < self::TAG_BYTES) {
            throw new DecryptionException('Ciphertext is too short to be valid.');
        }

        $plaintext = sodium_crypto_aead_chacha20poly1305_ietf_decrypt($ciphertext, '', $nonce, $key);
        if (false === $plaintext) {
            throw new DecryptionException('Symmetric decryption failed: wrong key or corrupted ciphertext.');
        }

        return $plaintext;
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return \function_exists('sodium_crypto_aead_chacha20poly1305_ietf_decrypt');
    }

    #[\Override]
    public function name(): string
    {
        return 'sodium';
    }
}

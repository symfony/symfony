<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine\OpenSsl;

use Symfony\Component\Encryption\Engine\AbstractSymmetricEngine;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\EncryptionException;

/**
 * ChaCha20-Poly1305 (IETF) authenticated encryption via OpenSSL.
 *
 * Output matches libsodium byte-for-byte (RFC 8439): the 16-byte Poly1305 tag
 * is appended to the ciphertext.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class OpenSslSymmetricEngine extends AbstractSymmetricEngine
{
    private const CIPHER = 'chacha20-poly1305';

    #[\Override]
    public function encrypt(string $plaintext, string $key, string $nonce): string
    {
        $this->assertKeyAndNonce($key, $nonce);

        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, \OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_BYTES);
        if (false === $ciphertext) {
            throw new EncryptionException('OpenSSL ChaCha20-Poly1305 encryption failed.');
        }

        return $ciphertext.$tag;
    }

    #[\Override]
    public function decrypt(string $ciphertext, string $key, string $nonce): string
    {
        $this->assertKeyAndNonce($key, $nonce);

        if (\strlen($ciphertext) < self::TAG_BYTES) {
            throw new DecryptionException('Ciphertext is too short to be valid.');
        }

        $tag = substr($ciphertext, -self::TAG_BYTES);
        $body = substr($ciphertext, 0, -self::TAG_BYTES);

        $plaintext = openssl_decrypt($body, self::CIPHER, $key, \OPENSSL_RAW_DATA, $nonce, $tag, '');
        if (false === $plaintext) {
            throw new DecryptionException('Symmetric decryption failed: wrong key or corrupted ciphertext.');
        }

        return $plaintext;
    }

    #[\Override]
    public function isAvailable(): bool
    {
        if (!\extension_loaded('openssl')) {
            return false;
        }

        /** @var list<string> $cipherMethods */
        $cipherMethods = openssl_get_cipher_methods();

        return \in_array(self::CIPHER, array_map('strtolower', $cipherMethods), true);
    }

    #[\Override]
    public function name(): string
    {
        return 'openssl';
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Local;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\KeyLoaderInterface;

/**
 * Local libsodium-backed KMS suitable for development and tests.
 *
 * Encryption uses XChaCha20-Poly1305-IETF AEAD with a random 24-byte nonce.
 * The wire format of a {@see Ciphertext} blob is:
 *
 *     [1-byte version = 0x01] [24-byte nonce] [ciphertext || 16-byte tag]
 *
 * AAD is forwarded as-is to the AEAD primitive: callers are responsible for
 * any serialization (e.g. canonical JSON for a structured map).
 *
 * Use a blind index for equality lookups while encrypting values with a random nonce.
 *
 * Key material is sourced through a {@see KeyLoaderInterface} so callers can
 * pick between an in-memory map, a directory of files, or any custom loader.
 * Length validation happens lazily on first use of each key.
 *
 * On the decrypt path, {@see KeyNotFoundException} is masked as
 * {@see DecryptionFailedException} so that an unknown key id cannot be
 * distinguished from a tampered ciphertext (key-enumeration oracle hardening).
 *
 * The `sodium` extension is bundled with PHP since 7.2 but it can still be
 * disabled at compile time, hence the runtime guard at construction.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class SodiumKms implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    private const int VERSION = 0x01;

    public function __construct(private readonly KeyLoaderInterface $keys)
    {
        if (!\extension_loaded('sodium')) {
            throw new LogicException('The "sodium" PHP extension is required to use the SodiumKms backend.');
        }
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = ''): Ciphertext
    {
        $key = $this->lookup($keyId);
        $nonce = random_bytes(\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $sealed = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);

        return new Ciphertext(\chr(self::VERSION).$nonce.$sealed, $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        try {
            $key = $this->lookup($ciphertext->keyId);
        } catch (KeyNotFoundException) {
            throw new DecryptionFailedException();
        }

        $minLength = 1 + \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES + \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;
        if (\strlen($ciphertext->blob) < $minLength || self::VERSION !== \ord($ciphertext->blob[0])) {
            throw new DecryptionFailedException();
        }

        $nonce = substr($ciphertext->blob, 1, \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $sealed = substr($ciphertext->blob, 1 + \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($sealed, $aad, $nonce, $key);
        if (false === $plaintext) {
            throw new DecryptionFailedException();
        }

        return $plaintext;
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        if ($length < 16) {
            throw new InvalidArgumentException(\sprintf('Data key length must be at least 16 bytes, %d given.', $length));
        }

        $plaintext = random_bytes($length);

        return new DataKey($plaintext, $this->encrypt($keyId, $plaintext, $aad));
    }

    public function wrapDataKey(string $keyId, #[\SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
    {
        return $this->encrypt($keyId, $dataKey, $aad);
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }

    private function lookup(string $keyId): string
    {
        $material = $this->keys->load($keyId);
        if (\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== \strlen($material)) {
            throw new InvalidArgumentException(\sprintf('Key "%s" must be exactly %d bytes long, %d given.', $keyId, \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, \strlen($material)));
        }

        return $material;
    }
}

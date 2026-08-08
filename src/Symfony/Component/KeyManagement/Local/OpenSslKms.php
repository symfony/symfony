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
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\KeyLoader\KeyLoaderInterface;

/**
 * Local KMS backed by ext-openssl (AES-256-GCM AEAD), useful when libsodium
 * is not available or when an OpenSSL-compatible wire format is required.
 *
 * The wire format of a {@see Ciphertext} blob is:
 *
 *     [1-byte version = 0x01] [12-byte IV] [16-byte tag] [ciphertext]
 *
 * AAD is forwarded as-is to the AEAD primitive: callers are responsible for
 * any serialization (e.g. canonical JSON for a structured map).
 *
 * Deterministic mode: when `$deterministic` is true, the IV is derived from
 * the AAD and the plaintext via `HMAC-SHA512(len(aad) || aad || plaintext,
 * key)` truncated to 12 bytes, yielding the same ciphertext for the same
 * (key, aad, plaintext) triple. This enables exact-match indexing on
 * encrypted columns at the cost of leaking equality. Decrypt is unchanged:
 * the IV is read from the blob either way.
 *
 * Key material is sourced through a {@see KeyLoaderInterface} so callers can
 * pick between an in-memory map, a directory of files, or any custom loader.
 * Length validation happens lazily on first use of each key (32 bytes for
 * AES-256-GCM).
 *
 * On the decrypt path, {@see KeyNotFoundException} is masked as
 * {@see DecryptionFailedException} so that an unknown key id cannot be
 * distinguished from a tampered ciphertext (key-enumeration oracle hardening).
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class OpenSslKms implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    private const int VERSION = 0x01;
    private const string CIPHER = 'aes-256-gcm';
    private const int KEY_BYTES = 32;
    private const int IV_BYTES = 12;
    private const int TAG_BYTES = 16;

    public function __construct(private readonly KeyLoaderInterface $keys)
    {
        if (!\extension_loaded('openssl')) {
            throw new LogicException('The "openssl" PHP extension is required to use the OpenSslKms backend.');
        }
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        $key = $this->lookup($keyId);
        $iv = $deterministic ? self::syntheticIv($key, $aad, $plaintext) : random_bytes(self::IV_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, \OPENSSL_RAW_DATA, $iv, $tag, $aad, self::TAG_BYTES);
        if (false === $ciphertext) {
            throw new RuntimeException('OpenSSL encryption failed.');
        }

        return new Ciphertext(\chr(self::VERSION).$iv.$tag.$ciphertext, $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        try {
            $key = $this->lookup($ciphertext->keyId);
        } catch (KeyNotFoundException) {
            throw new DecryptionFailedException();
        }

        if (\strlen($ciphertext->blob) < 1 + self::IV_BYTES + self::TAG_BYTES || self::VERSION !== \ord($ciphertext->blob[0])) {
            throw new DecryptionFailedException();
        }

        $iv = substr($ciphertext->blob, 1, self::IV_BYTES);
        $tag = substr($ciphertext->blob, 1 + self::IV_BYTES, self::TAG_BYTES);
        $sealed = substr($ciphertext->blob, 1 + self::IV_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt($sealed, self::CIPHER, $key, \OPENSSL_RAW_DATA, $iv, $tag, $aad);
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

        return new DataKey($plaintext, $this->encrypt($keyId, $plaintext, $aad, false));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }

    /**
     * Derives the IV of a deterministic encryption from the AAD as well as the
     * plaintext, the way SIV constructions do. Two encryptions differing only
     * by their AAD must not land on the same IV: the pair of GCM tags they
     * would produce under one key reveals the authentication subkey, and with
     * it the ability to forge tags for anything encrypted under that key. The
     * length prefix keeps the concatenation unambiguous, so no (aad, plaintext)
     * pair can be read as another one. It spans 8 big-endian bytes, as the `J`
     * format code would write them, that one being unavailable on 32-bit builds
     * where no string is ever long enough for the high word to be anything but
     * zero.
     */
    private static function syntheticIv(#[\SensitiveParameter] string $key, string $aad, #[\SensitiveParameter] string $plaintext): string
    {
        $prefix = pack('NN', \PHP_INT_SIZE >= 8 ? \strlen($aad) >> 32 : 0, \strlen($aad));

        return substr(hash_hmac('sha512', $prefix.$aad.$plaintext, $key, true), 0, self::IV_BYTES);
    }

    private function lookup(string $keyId): string
    {
        $material = $this->keys->load($keyId);
        if (self::KEY_BYTES !== \strlen($material)) {
            throw new InvalidArgumentException(\sprintf('Key "%s" must be exactly %d bytes long, %d given.', $keyId, self::KEY_BYTES, \strlen($material)));
        }

        return $material;
    }
}

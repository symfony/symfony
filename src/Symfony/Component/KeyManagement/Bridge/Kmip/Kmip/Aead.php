<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Kmip;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Encrypts and decrypts with authenticated ciphers through the KMIP Encrypt and Decrypt operations.
 *
 * KMIP carries the encrypted data, the IV and the authentication tag in separate fields, which the bridge stores in one blob.
 * The blob starts with a header made of a format byte (0x01), the KMIP Cryptographic Algorithm and Block Cipher Mode on four bytes each, and the IV length on one byte.
 * The IV, the 16-byte authentication tag and the encrypted data follow, in that order.
 *
 * The header is sent as the start of the authenticated additional data, so changing any of it makes decryption fail.
 * Storing the algorithm and the mode lets another cipher be added without changing the format.
 * Storing the IV length keeps earlier ciphertexts readable after the configured length changes, as long as the server accepts it.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class Aead
{
    /**
     * The IV length NIST SP 800-38D recommends for GCM.
     */
    public const int DEFAULT_IV_LENGTH = 12;

    private const string FORMAT = "\x01";
    private const int AES_ALGORITHM = 0x03;
    private const int CHACHA20_POLY1305_ALGORITHM = 0x1E;
    private const string NO_BLOCK_CIPHER_MODE = "\0\0\0\0";
    private const string GCM_BLOCK_CIPHER_MODE = "\0\0\0\x09";
    private const int HEADER_LENGTH = 10;
    private const int IV_LENGTH_OFFSET = 9;
    private const int MIN_IV_LENGTH = 12;
    /**
     * The header stores the IV length in one unsigned byte.
     */
    private const int MAX_IV_LENGTH = 255;
    private const int TAG_LENGTH = 16;
    private const int BITS_PER_BYTE = 8;

    private readonly int $algorithm;
    private readonly string $mode;

    public function __construct(
        private readonly int $ivLength = self::DEFAULT_IV_LENGTH,
        string $cipher = 'aes-gcm',
    ) {
        [$this->algorithm, $this->mode] = match ($cipher) {
            'aes-gcm' => [self::AES_ALGORITHM, self::GCM_BLOCK_CIPHER_MODE],
            'chacha20-poly1305' => [self::CHACHA20_POLY1305_ALGORITHM, self::NO_BLOCK_CIPHER_MODE],
            default => throw new InvalidArgumentException('Supported KMIP ciphers are "aes-gcm" and "chacha20-poly1305".'),
        };
        if ('aes-gcm' === $cipher) {
            if ($ivLength < self::MIN_IV_LENGTH || $ivLength > self::MAX_IV_LENGTH) {
                throw new InvalidArgumentException(\sprintf('The KMIP AES-GCM IV length must be between %d and %d bytes.', self::MIN_IV_LENGTH, self::MAX_IV_LENGTH));
            }
        } elseif (self::DEFAULT_IV_LENGTH !== $ivLength) {
            throw new InvalidArgumentException('The KMIP ChaCha20-Poly1305 IV length must be 12 bytes.');
        }
    }

    public function encrypt(RequestClientInterface $client, string $keyId, #[\SensitiveParameter] string $plaintext, #[\SensitiveParameter] string $aad): Ciphertext
    {
        $header = self::FORMAT.pack('N', $this->algorithm).$this->mode.\chr($this->ivLength);
        $iv = random_bytes($this->ivLength);
        $fields = $client->request(RequestClientInterface::ENCRYPT, self::payload($keyId, $plaintext, $iv, $header.$aad, $this->algorithm, $this->mode));

        if (!hash_equals($keyId, self::required($fields, RequestClientInterface::UNIQUE_IDENTIFIER, Ttlv::TEXT))) {
            throw new RuntimeException('KMIP returned a different key identifier.');
        }

        $encrypted = self::required($fields, RequestClientInterface::DATA, Ttlv::BYTES);
        $tag = self::required($fields, RequestClientInterface::TAG, Ttlv::BYTES);

        if (self::TAG_LENGTH !== \strlen($tag) || \strlen($encrypted) !== \strlen($plaintext)) {
            throw new RuntimeException('KMIP returned invalid authenticated ciphertext.');
        }

        if (isset($fields[RequestClientInterface::IV]) && !hash_equals($iv, Ttlv::requireType($fields[RequestClientInterface::IV], RequestClientInterface::IV, Ttlv::BYTES))) {
            throw new RuntimeException('KMIP returned a different encryption IV.');
        }

        return new Ciphertext($header.$iv.$tag.$encrypted, $keyId);
    }

    public function decrypt(RequestClientInterface $client, #[\SensitiveParameter] Ciphertext $ciphertext, #[\SensitiveParameter] string $aad): string
    {
        $blob = $ciphertext->blob;

        if (\strlen($blob) < self::HEADER_LENGTH + self::TAG_LENGTH) {
            throw new DecryptionFailedException();
        }

        $header = substr($blob, 0, self::HEADER_LENGTH);
        ['algorithm' => $algorithm] = unpack('Nalgorithm', $header, 1);
        $mode = substr($header, 5, 4);
        $isAesGcm = self::AES_ALGORITHM === $algorithm && self::GCM_BLOCK_CIPHER_MODE === $mode;
        $isChaCha20Poly1305 = self::CHACHA20_POLY1305_ALGORITHM === $algorithm && self::NO_BLOCK_CIPHER_MODE === $mode;
        $ivLength = \ord($header[self::IV_LENGTH_OFFSET]);

        if (self::FORMAT !== $header[0] || !$isAesGcm && !$isChaCha20Poly1305 || $ivLength < self::MIN_IV_LENGTH || !$isAesGcm && self::DEFAULT_IV_LENGTH !== $ivLength || \strlen($blob) < self::HEADER_LENGTH + $ivLength + self::TAG_LENGTH) {
            throw new DecryptionFailedException();
        }

        $iv = substr($blob, self::HEADER_LENGTH, $ivLength);
        $tag = substr($blob, self::HEADER_LENGTH + $ivLength, self::TAG_LENGTH);
        $encrypted = substr($blob, self::HEADER_LENGTH + $ivLength + self::TAG_LENGTH);
        $fields = $client->request(RequestClientInterface::DECRYPT, self::payload($ciphertext->keyId, $encrypted, $iv, $header.$aad, $algorithm, $mode).Ttlv::bytes(RequestClientInterface::TAG, $tag));

        if (!hash_equals($ciphertext->keyId, self::required($fields, RequestClientInterface::UNIQUE_IDENTIFIER, Ttlv::TEXT))) {
            throw new RuntimeException('KMIP returned a different key identifier.');
        }

        $plaintext = self::required($fields, RequestClientInterface::DATA, Ttlv::BYTES);

        if (\strlen($plaintext) !== \strlen($encrypted)) {
            throw new RuntimeException('KMIP returned invalid plaintext length.');
        }

        return $plaintext;
    }

    private static function payload(string $keyId, #[\SensitiveParameter] string $data, #[\SensitiveParameter] string $iv, #[\SensitiveParameter] string $aad, int $algorithm = self::AES_ALGORITHM, string $mode = self::GCM_BLOCK_CIPHER_MODE): string
    {
        $parameters = self::NO_BLOCK_CIPHER_MODE === $mode ? '' : Ttlv::encode(RequestClientInterface::BLOCK_CIPHER_MODE, Ttlv::ENUMERATION, $mode);
        $parameters .= Ttlv::integer(RequestClientInterface::CRYPTOGRAPHIC_ALGORITHM, $algorithm, Ttlv::ENUMERATION);
        if (self::AES_ALGORITHM === $algorithm && self::GCM_BLOCK_CIPHER_MODE === $mode) {
            $parameters .= Ttlv::integer(RequestClientInterface::IV_LENGTH, \strlen($iv) * self::BITS_PER_BYTE)
                .Ttlv::integer(RequestClientInterface::TAG_LENGTH, self::TAG_LENGTH);
        }

        return Ttlv::text(RequestClientInterface::UNIQUE_IDENTIFIER, $keyId)
            .Ttlv::structure(RequestClientInterface::CRYPTOGRAPHIC_PARAMETERS, $parameters)
            .Ttlv::bytes(RequestClientInterface::DATA, $data)
            .Ttlv::bytes(RequestClientInterface::IV, $iv)
            .Ttlv::bytes(RequestClientInterface::AAD, $aad);
    }

    /**
     * @param array<int, array{tag: int, type: int, value: string}> $fields
     */
    private static function required(#[\SensitiveParameter] array $fields, int $tag, int $type): string
    {
        if (!isset($fields[$tag])) {
            throw new RuntimeException('Missing KMIP response payload field.');
        }

        return Ttlv::requireType($fields[$tag], $tag, $type);
    }
}

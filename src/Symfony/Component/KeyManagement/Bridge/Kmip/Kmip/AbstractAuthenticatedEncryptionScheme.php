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

use Symfony\Component\KeyManagement\Bridge\Kmip\KmipEncryptionSchemeInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipRequestClientInterface;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Shares the KMIP Encrypt and Decrypt flow used by the built-in authenticated schemes.
 *
 * Encryption generates a fresh IV of the scheme's configured length and validates the server's returned key identifier, ciphertext length, authentication tag and any returned IV. Decryption sends the stored IV and tag back to the server and checks the returned identifier and plaintext length.
 *
 * The raw ciphertext blob stores one byte with the IV length, then the IV, tag and encrypted data. Decryption reads the stored length and asks the scheme whether it is supported. AES-GCM accepts earlier ciphertext after its configured IV length changes if the server still accepts that length; the other built-in schemes require their fixed 12-byte IV. Each subclass supplies the Cryptographic Parameters that identify its algorithm and any applicable mode; the bridge adds and authenticates its scheme-name header outside this class.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
abstract class AbstractAuthenticatedEncryptionScheme implements KmipEncryptionSchemeInterface
{
    private const int IV_LENGTH_OFFSET = 0;
    private const int IV_OFFSET = 1;

    /**
     * Length in bytes of the 128-bit authentication tag returned by the KMIP server.
     */
    private const int TAG_LENGTH = 16;

    /**
     * An empty regular expression in Unicode mode checks UTF-8 validity.
     */
    private const string UTF8_VALIDATION_PATTERN = '//u';

    final public function encrypt(KmipRequestClientInterface $client, string $keyId, #[\SensitiveParameter] string $plaintext, #[\SensitiveParameter] string $aad): Ciphertext
    {
        $ivLength = $this->ivLength();
        $iv = random_bytes($ivLength);
        $fields = $client->request(KmipRequestClientInterface::ENCRYPT, $this->payload($keyId, $plaintext, $iv, $aad));
        $returnedId = self::required($fields, KmipRequestClientInterface::UNIQUE_IDENTIFIER, Ttlv::TEXT);
        if ('' === $returnedId || !preg_match(self::UTF8_VALIDATION_PATTERN, $returnedId)) {
            throw new RuntimeException('KMIP returned an invalid key identifier.');
        }
        $encrypted = self::required($fields, KmipRequestClientInterface::DATA, Ttlv::BYTES);
        $tag = self::required($fields, KmipRequestClientInterface::TAG, Ttlv::BYTES);
        if (self::TAG_LENGTH !== \strlen($tag) || \strlen($encrypted) !== \strlen($plaintext)) {
            throw new RuntimeException('KMIP returned invalid authenticated ciphertext.');
        }
        if (isset($fields[KmipRequestClientInterface::IV]) && !hash_equals($iv, Ttlv::requireType($fields[KmipRequestClientInterface::IV], KmipRequestClientInterface::IV, Ttlv::BYTES))) {
            throw new RuntimeException('KMIP returned a different encryption IV.');
        }

        return new Ciphertext(\chr($ivLength).$iv.$tag.$encrypted, $returnedId);
    }

    final public function decrypt(KmipRequestClientInterface $client, #[\SensitiveParameter] Ciphertext $ciphertext, #[\SensitiveParameter] string $aad): string
    {
        $blob = $ciphertext->blob;
        if (\strlen($blob) < self::IV_OFFSET + self::TAG_LENGTH) {
            throw new DecryptionFailedException();
        }
        $ivLength = \ord($blob[self::IV_LENGTH_OFFSET]);
        if (!$this->supportsIvLength($ivLength) || \strlen($blob) < self::IV_OFFSET + $ivLength + self::TAG_LENGTH) {
            throw new DecryptionFailedException();
        }
        $iv = substr($blob, self::IV_OFFSET, $ivLength);
        $tag = substr($blob, self::IV_OFFSET + $ivLength, self::TAG_LENGTH);
        $encrypted = substr($blob, self::IV_OFFSET + $ivLength + self::TAG_LENGTH);
        $fields = $client->request(KmipRequestClientInterface::DECRYPT, $this->payload($ciphertext->keyId, $encrypted, $iv, $aad).Ttlv::bytes(KmipRequestClientInterface::TAG, $tag));
        if (!hash_equals($ciphertext->keyId, self::required($fields, KmipRequestClientInterface::UNIQUE_IDENTIFIER, Ttlv::TEXT))) {
            throw new RuntimeException('KMIP returned a different key identifier.');
        }
        $plaintext = self::required($fields, KmipRequestClientInterface::DATA, Ttlv::BYTES);
        if (\strlen($plaintext) !== \strlen($encrypted)) {
            throw new RuntimeException('KMIP returned invalid plaintext length.');
        }

        return $plaintext;
    }

    /**
     * Returns the IV length in bytes for new ciphertext.
     */
    abstract protected function ivLength(): int;

    /**
     * Checks whether a stored ciphertext's IV length is valid for this scheme.
     */
    protected function supportsIvLength(int $ivLength): bool
    {
        return $this->ivLength() === $ivLength;
    }

    /**
     * Builds the scheme-specific Cryptographic Parameters for Encrypt and Decrypt requests.
     *
     * Include the algorithm and any required mode, IV or authentication tag settings. The IV length is read from the ciphertext when decrypting.
     * Return one complete binary TTLV Cryptographic Parameters Structure item ready to append to the request payload.
     *
     * @return string The encoded TTLV Structure item
     */
    abstract protected function cryptographicParameters(int $ivLength): string;

    private function payload(string $keyId, #[\SensitiveParameter] string $data, #[\SensitiveParameter] string $iv, #[\SensitiveParameter] string $aad): string
    {
        return Ttlv::text(KmipRequestClientInterface::UNIQUE_IDENTIFIER, $keyId)
            .$this->cryptographicParameters(\strlen($iv))
            .Ttlv::bytes(KmipRequestClientInterface::DATA, $data)
            .Ttlv::bytes(KmipRequestClientInterface::IV, $iv)
            .('' !== $aad ? Ttlv::bytes(KmipRequestClientInterface::AAD, $aad) : '');
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

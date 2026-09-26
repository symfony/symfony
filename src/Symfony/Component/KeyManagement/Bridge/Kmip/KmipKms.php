<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip;

use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcm;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\FrameTooLarge;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\OperationFailed;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\RequestClientInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

/**
 * Encrypts and decrypts with AES-GCM under a symmetric key a KMIP server holds.
 *
 * Build it with {@see KmipKmsFactory}. Each operation opens a verified mutual TLS connection. Direct encryption sends the plaintext to the server, and {@see generateDataKey()} sends a locally generated data key; the master key stays there. Deterministic encryption is unsupported.
 *
 * The caller's AAD is authenticated together with the header of the stored ciphertext, see {@see AesGcm}.
 *
 * When a server reports KMIP General Failure, decrypt and unwrap preserve that operational error. PyKMIP also uses General Failure for a bad authentication tag, so callers using that server may receive {@see \Symfony\Component\KeyManagement\Exception\RuntimeException} rather than {@see DecryptionFailedException} for tampered data or incorrect AAD.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
final class KmipKms implements EncrypterInterface, DecrypterInterface, DataKeyGeneratorInterface
{
    /**
     * Minimum locally generated data key size of 128 bits.
     */
    private const int MIN_DATA_KEY_LENGTH = 16;
    /**
     * Default locally generated data key size of 256 bits.
     */
    private const int DEFAULT_DATA_KEY_LENGTH = 32;
    /**
     * Space reserved for KMIP framing and other request fields around a key identifier or data key.
     */
    private const int FRAME_SIZE_RESERVE = 1024;
    /**
     * Limits key identifiers included in exception messages.
     */
    private const int MAX_DISPLAY_KEY_ID_LENGTH = 128;
    private const int REASON_ITEM_NOT_FOUND = 0x01;
    private const int REASON_CRYPTOGRAPHIC_FAILURE = 0x0A;
    private const int REASON_OBJECT_DESTROYED = 0x36;
    private const int REASON_OBJECT_NOT_FOUND = 0x37;
    /**
     * An empty regular expression in Unicode mode checks UTF-8 validity.
     */
    private const string UTF8_VALIDATION_PATTERN = '//u';
    /**
     * Unicode control and format characters are escaped in displayed key identifiers.
     */
    private const string CONTROL_OR_FORMAT_CHARACTER_PATTERN = '/[\p{Cc}\p{Cf}]/u';
    private const string CONTROL_CHARACTER_ESCAPE_PREFIX = '\\x';

    private readonly AesGcm $cipher;

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestClientInterface $requestClient,
        int $ivLength = AesGcm::DEFAULT_IV_LENGTH,
    ) {
        $this->cipher = new AesGcm($ivLength);
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, #[\SensitiveParameter] string $aad = '', bool $deterministic = false): Ciphertext
    {
        if ($deterministic) {
            throw new UnsupportedOperationException('KMIP encryption does not support deterministic encryption.');
        }
        self::validateKeyId($keyId);

        try {
            return $this->cipher->encrypt($this->requestClient, $keyId, $plaintext, $aad);
        } catch (OperationFailed $e) {
            if ($e->hasOperation && \in_array($e->reason, [self::REASON_ITEM_NOT_FOUND, self::REASON_OBJECT_NOT_FOUND], true)) {
                throw new KeyNotFoundException(self::safeKeyId($keyId), $e);
            }

            throw $e;
        }
    }

    public function decrypt(#[\SensitiveParameter] Ciphertext $ciphertext, #[\SensitiveParameter] string $aad = ''): string
    {
        try {
            self::validateKeyId($ciphertext->keyId);
        } catch (InvalidArgumentException) {
            throw new DecryptionFailedException();
        }

        try {
            return $this->cipher->decrypt($this->requestClient, $ciphertext, $aad);
        } catch (FrameTooLarge) {
            throw new DecryptionFailedException();
        } catch (OperationFailed $e) {
            // PyKMIP also reports invalid authentication tags as General Failure, but that reason can mean a server error.
            if ($e->hasOperation && \in_array($e->reason, [self::REASON_ITEM_NOT_FOUND, self::REASON_CRYPTOGRAPHIC_FAILURE, self::REASON_OBJECT_DESTROYED, self::REASON_OBJECT_NOT_FOUND], true)) {
                throw new DecryptionFailedException();
            }

            throw $e;
        }
    }

    public function generateDataKey(string $keyId, int $length = self::DEFAULT_DATA_KEY_LENGTH, #[\SensitiveParameter] string $aad = ''): DataKey
    {
        if ($length < self::MIN_DATA_KEY_LENGTH || $length > Ttlv::MAX_FRAME - self::FRAME_SIZE_RESERVE) {
            throw new InvalidArgumentException(\sprintf('The KMIP data key length must be at least %d bytes and fit in one frame.', self::MIN_DATA_KEY_LENGTH));
        }
        $plaintext = random_bytes($length);

        return new DataKey($plaintext, $this->encrypt($keyId, $plaintext, $aad));
    }

    public function unwrapDataKey(#[\SensitiveParameter] Ciphertext $wrapped, #[\SensitiveParameter] string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }

    private static function validateKeyId(string $keyId): void
    {
        if ('' === $keyId || \strlen($keyId) > Ttlv::MAX_FRAME - self::FRAME_SIZE_RESERVE || !preg_match(self::UTF8_VALIDATION_PATTERN, $keyId)) {
            throw new InvalidArgumentException('The KMIP key identifier must be a nonempty UTF-8 Text String that fits in one frame.');
        }
    }

    private static function safeKeyId(string $keyId): string
    {
        preg_match('/^.{0,'.self::MAX_DISPLAY_KEY_ID_LENGTH.'}/us', $keyId, $display);

        return preg_replace_callback(self::CONTROL_OR_FORMAT_CHARACTER_PATTERN, self::escapeControlCharacter(...), $display[0] ?? '') ?? '';
    }

    /**
     * @param non-empty-list<string> $match
     */
    private static function escapeControlCharacter(array $match): string
    {
        return self::CONTROL_CHARACTER_ESCAPE_PREFIX.strtoupper(bin2hex($match[0]));
    }
}

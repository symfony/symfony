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

use Symfony\Component\KeyManagement\Bridge\Kmip\KmipRequestClientInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Requests AES-GCM encryption through the KMIP Cryptographic Parameters.
 *
 * The parameters identify the standard AES algorithm and GCM block cipher mode, with a configurable IV length and a 16-byte authentication tag. New ciphertext uses a 16-byte IV by default. The shared authenticated scheme stores each IV's length so decryption can read ciphertext written before that setting changed.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
final class AesGcmEncryptionScheme extends AbstractAuthenticatedEncryptionScheme
{
    private const string SCHEME_NAME = 'aes-gcm';
    private const int GCM_BLOCK_CIPHER_MODE = 9;
    private const int AES_ALGORITHM = 3;
    private const int MIN_IV_LENGTH_BYTES = 12;

    /**
     * The ciphertext format stores the IV length in one unsigned byte.
     */
    private const int MAX_IV_LENGTH_BYTES = 255;
    private const int DEFAULT_IV_LENGTH_BYTES = 16;
    private const int BITS_PER_BYTE = 8;
    private const int TAG_LENGTH_BYTES = 16;

    public function __construct(private readonly int $ivLength = self::DEFAULT_IV_LENGTH_BYTES)
    {
        if (!$this->isValidIvLength($ivLength)) {
            throw new InvalidArgumentException(\sprintf('The KMIP AES-GCM IV length must be between %d and %d bytes.', self::MIN_IV_LENGTH_BYTES, self::MAX_IV_LENGTH_BYTES));
        }
    }

    public function name(): string
    {
        return self::SCHEME_NAME;
    }

    protected function ivLength(): int
    {
        return $this->ivLength;
    }

    protected function supportsIvLength(int $ivLength): bool
    {
        return $this->isValidIvLength($ivLength);
    }

    protected function cryptographicParameters(int $ivLength): string
    {
        return Ttlv::structure(KmipRequestClientInterface::CRYPTOGRAPHIC_PARAMETERS,
            Ttlv::integer(KmipRequestClientInterface::BLOCK_CIPHER_MODE, self::GCM_BLOCK_CIPHER_MODE, Ttlv::ENUMERATION)
            .Ttlv::integer(KmipRequestClientInterface::CRYPTOGRAPHIC_ALGORITHM, self::AES_ALGORITHM, Ttlv::ENUMERATION)
            .Ttlv::integer(KmipRequestClientInterface::IV_LENGTH, $ivLength * self::BITS_PER_BYTE)
            .Ttlv::integer(KmipRequestClientInterface::TAG_LENGTH, self::TAG_LENGTH_BYTES)
        );
    }

    private function isValidIvLength(int $ivLength): bool
    {
        return self::MIN_IV_LENGTH_BYTES <= $ivLength && self::MAX_IV_LENGTH_BYTES >= $ivLength;
    }
}

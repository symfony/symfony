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
 * Requests AES-GCM-SIV with a server-specific KMIP Block Cipher Mode value.
 *
 * KMIP 1.4, 2.0 and 2.1 do not assign a standard mode value to AES-GCM-SIV. The constructor requires a value in the KMIP vendor extension range, so each server's documented value can be supplied without making one vendor the default. Pass it as a hexadecimal string (for example, "0x80000002") for compatibility with 32-bit PHP; an integer also works on 64-bit PHP.
 *
 * The optional scheme name distinguishes instances with different mode values in the same registry. Keep each name mapped to its original mode while ciphertext written with that name remains stored.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
final class AesGcmSivEncryptionScheme extends AbstractAuthenticatedEncryptionScheme
{
    private const int AES_ALGORITHM = 3;
    private const int IV_LENGTH_BYTES = 12;
    /**
     * First value in KMIP's vendor extension range for enumerations.
     */
    private const string MIN_VENDOR_BLOCK_CIPHER_MODE = '0x80000000';
    /**
     * Last value in KMIP's vendor extension range for enumerations.
     */
    private const string MAX_VENDOR_BLOCK_CIPHER_MODE = '0x8FFFFFFF';
    private const string VENDOR_BLOCK_CIPHER_MODE_PATTERN = '/^0x8[0-9A-Fa-f]{7}$/D';

    private readonly string $blockCipherMode;

    public function __construct(int|string $blockCipherMode, private readonly string $schemeName = 'aes-gcm-siv')
    {
        $mode = \is_int($blockCipherMode) ? \sprintf('0x%08X', $blockCipherMode) : $blockCipherMode;
        if (!preg_match(self::VENDOR_BLOCK_CIPHER_MODE_PATTERN, $mode)) {
            throw new InvalidArgumentException('The KMIP AES-GCM-SIV block cipher mode must be in the vendor extension range '.self::MIN_VENDOR_BLOCK_CIPHER_MODE.' to '.self::MAX_VENDOR_BLOCK_CIPHER_MODE.'.');
        }

        $this->blockCipherMode = $mode;
    }

    public function name(): string
    {
        return $this->schemeName;
    }

    protected function ivLength(): int
    {
        return self::IV_LENGTH_BYTES;
    }

    protected function cryptographicParameters(int $ivLength): string
    {
        return Ttlv::structure(KmipRequestClientInterface::CRYPTOGRAPHIC_PARAMETERS,
            Ttlv::integer(KmipRequestClientInterface::BLOCK_CIPHER_MODE, $this->blockCipherMode, Ttlv::ENUMERATION)
            .Ttlv::integer(KmipRequestClientInterface::CRYPTOGRAPHIC_ALGORITHM, self::AES_ALGORITHM, Ttlv::ENUMERATION)
        );
    }
}

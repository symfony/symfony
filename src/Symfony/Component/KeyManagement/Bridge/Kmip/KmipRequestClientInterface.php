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

use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;

/**
 * Sends one KMIP operation using a TTLV-encoded request payload.
 *
 * Encryption schemes use this interface to send Encrypt and Decrypt requests without managing TLS or the KMIP message envelope. The constants name the operations and TTLV fields needed by an authenticated encryption scheme. Use {@see Ttlv} to encode request payloads and validate response values.
 * A successful response is returned as payload items keyed by their numeric KMIP tags; an operation failure raises an exception.
 * Request payloads may contain plaintext. Implementations must mark the payload parameter as sensitive so exception traces redact it.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
interface KmipRequestClientInterface
{
    /**
     * KMIP Encrypt operation value.
     */
    public const int ENCRYPT = 0x1F;
    /**
     * KMIP Decrypt operation value.
     */
    public const int DECRYPT = 0x20;
    /**
     * Block Cipher Mode field in Cryptographic Parameters.
     */
    public const int BLOCK_CIPHER_MODE = 0x420011;
    /**
     * Cryptographic Algorithm field in Cryptographic Parameters.
     */
    public const int CRYPTOGRAPHIC_ALGORITHM = 0x420028;
    /**
     * Structure containing the operation's cryptographic settings.
     */
    public const int CRYPTOGRAPHIC_PARAMETERS = 0x42002B;
    /**
     * Identifier of the server-held key used by an operation.
     */
    public const int UNIQUE_IDENTIFIER = 0x420094;
    /**
     * Input or output data field of an Encrypt or Decrypt operation.
     */
    public const int DATA = 0x4200C2;
    /**
     * IV/Counter/Nonce field carrying the initialization vector.
     */
    public const int IV = 0x42003D;
    /**
     * IV Length field in Cryptographic Parameters, measured in bits.
     */
    public const int IV_LENGTH = 0x4200CD;
    /**
     * Authentication Tag Length field in Cryptographic Parameters, measured in bytes.
     */
    public const int TAG_LENGTH = 0x4200CE;
    /**
     * Authenticated Encryption Additional Data field.
     */
    public const int AAD = 0x4200FE;
    /**
     * Authenticated Encryption Tag field.
     */
    public const int TAG = 0x4200FF;

    /**
     * @return array<int, array{tag: int, type: int, value: string}>
     */
    public function request(int $operation, #[\SensitiveParameter] string $payload): array;
}

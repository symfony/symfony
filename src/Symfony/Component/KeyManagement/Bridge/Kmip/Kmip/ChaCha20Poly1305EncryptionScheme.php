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

/**
 * Requests ChaCha20-Poly1305 encryption with its KMIP algorithm identifier.
 *
 * The algorithm identifier names the combined cipher and authenticator, so no Block Cipher Mode value is sent. The shared authenticated scheme supplies a random 96-bit IV and expects a 16-byte authentication tag.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
final class ChaCha20Poly1305EncryptionScheme extends AbstractAuthenticatedEncryptionScheme
{
    private const int CHACHA20_POLY1305_ALGORITHM = 0x1E;
    private const int IV_LENGTH_BYTES = 12;

    public function name(): string
    {
        return 'chacha20-poly1305';
    }

    protected function ivLength(): int
    {
        return self::IV_LENGTH_BYTES;
    }

    protected function cryptographicParameters(int $ivLength): string
    {
        return Ttlv::structure(KmipRequestClientInterface::CRYPTOGRAPHIC_PARAMETERS, Ttlv::integer(KmipRequestClientInterface::CRYPTOGRAPHIC_ALGORITHM, self::CHACHA20_POLY1305_ALGORITHM, Ttlv::ENUMERATION));
    }
}

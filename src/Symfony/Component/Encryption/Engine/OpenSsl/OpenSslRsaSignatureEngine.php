<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Engine\OpenSsl;

use Symfony\Component\Encryption\Exception\EncryptionException;

/**
 * RSASSA-PKCS#1 v1.5 (SHA-256) signatures via OpenSSL.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class OpenSslRsaSignatureEngine extends AbstractOpenSslSignatureEngine
{
    private const KEY_BITS = 3072;

    #[\Override]
    public function generateKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => self::KEY_BITS,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        if (false === $resource || !openssl_pkey_export($resource, $privatePem)) {
            throw new EncryptionException('RSA key generation failed.');
        }

        $details = openssl_pkey_get_details($resource);
        if (false === $details || !isset($details['key']) || !\is_string($details['key'])) {
            throw new EncryptionException('RSA key generation failed.');
        }

        return [$details['key'], $privatePem];
    }

    #[\Override]
    public function algorithm(): string
    {
        return 'rsa';
    }

    #[\Override]
    public function name(): string
    {
        return 'openssl';
    }
}

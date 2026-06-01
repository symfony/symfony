<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine\OpenSsl;

use Symfony\Component\Encryption\Exception\EncryptionException;

/**
 * ECDSA P-256 (SHA-256, ASN.1/DER) signatures via OpenSSL.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class OpenSslEcdsaSignatureEngine extends AbstractOpenSslSignatureEngine
{
    private const CURVE = 'prime256v1';

    #[\Override]
    public function generateKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => \OPENSSL_KEYTYPE_EC,
            'curve_name' => self::CURVE,
        ]);
        if (false === $resource || !openssl_pkey_export($resource, $privatePem)) {
            throw new EncryptionException('ECDSA key generation failed.');
        }

        $details = openssl_pkey_get_details($resource);
        if (false === $details || !isset($details['key']) || !\is_string($details['key'])) {
            throw new EncryptionException('ECDSA key generation failed.');
        }

        return [$details['key'], $privatePem];
    }

    #[\Override]
    public function algorithm(): string
    {
        return 'ecdsa-p256';
    }

    #[\Override]
    public function name(): string
    {
        return 'openssl';
    }
}

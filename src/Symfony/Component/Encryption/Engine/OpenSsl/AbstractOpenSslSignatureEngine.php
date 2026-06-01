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

use Symfony\Component\Encryption\Engine\SignatureEngineInterface;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * Shared OpenSSL signing/verification (SHA-256). Subclasses provide key
 * generation and the algorithm/name. OpenSSL signs RSA and ECDSA keys
 * identically via the digest-sign API.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
abstract class AbstractOpenSslSignatureEngine implements SignatureEngineInterface
{
    #[\Override]
    public function sign(string $message, string $privateKey): string
    {
        $key = openssl_pkey_get_private($privateKey);
        if (false === $key) {
            throw new InvalidKeyException('Invalid private key for signing.');
        }

        $signature = '';
        if (!openssl_sign($message, $signature, $key, \OPENSSL_ALGO_SHA256)) {
            throw new EncryptionException('Signing failed.');
        }

        return $signature;
    }

    #[\Override]
    public function verify(string $signature, string $message, string $publicKey): bool
    {
        $key = openssl_pkey_get_public($publicKey);
        if (false === $key) {
            return false;
        }

        return 1 === openssl_verify($message, $signature, $key, \OPENSSL_ALGO_SHA256);
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return \extension_loaded('openssl');
    }
}

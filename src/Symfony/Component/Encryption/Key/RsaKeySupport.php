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

namespace Symfony\Component\Encryption\Key;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * phpseclib-backed helpers for RSA PEM keys.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class RsaKeySupport
{
    /**
     * Derive the SPKI public-key PEM from a private-key PEM.
     */
    public static function publicPemFromPrivatePem(string $privatePem): string
    {
        try {
            $key = PublicKeyLoader::load($privatePem);
        } catch (\Throwable $e) {
            throw new InvalidKeyException('Invalid RSA private key.', 0, $e);
        }

        if (!$key instanceof RSA\PrivateKey) {
            throw new InvalidKeyException('Not an RSA private key.');
        }

        $publicKey = $key->getPublicKey();
        if (!$publicKey instanceof RSA\PublicKey) {
            throw new InvalidKeyException('Could not derive RSA public key.');
        }

        $pem = $publicKey->toString('PKCS8');
        if (!\is_string($pem)) {
            throw new InvalidKeyException('RSA public key serialization did not return a string.');
        }

        return $pem;
    }
}

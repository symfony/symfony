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

use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * phpseclib-backed helpers for EC (ECDSA) PEM keys.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class EcKeySupport
{
    /**
     * Derive the SPKI public-key PEM from an EC private-key PEM.
     */
    public static function publicPemFromPrivatePem(string $privatePem): string
    {
        try {
            $key = PublicKeyLoader::load($privatePem);
        } catch (\Throwable $e) {
            throw new InvalidKeyException('Invalid EC private key.', 0, $e);
        }

        if (!$key instanceof EC\PrivateKey) {
            throw new InvalidKeyException('Not an EC private key.');
        }

        $public = $key->getPublicKey();
        if (!$public instanceof EC\PublicKey) {
            throw new InvalidKeyException('Could not derive the EC public key.');
        }

        return $public->toString('PKCS8');
    }
}

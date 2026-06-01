<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine\Phpseclib;

use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;
use Symfony\Component\Encryption\Engine\SignatureEngineInterface;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * ECDSA P-256 (SHA-256, ASN.1/DER) signatures via phpseclib (the
 * sodium/openssl-free fallback). Interoperable with the OpenSSL engine.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class PhpseclibEcdsaSignatureEngine implements SignatureEngineInterface
{
    private const CURVE = 'secp256r1';

    #[\Override]
    public function generateKeyPair(): array
    {
        $private = EC::createKey(self::CURVE);
        $public = $private->getPublicKey();
        if (!$public instanceof EC\PublicKey) {
            throw new EncryptionException('ECDSA key generation failed.');
        }

        return [$public->toString('PKCS8'), $private->toString('PKCS8')];
    }

    #[\Override]
    public function sign(string $message, string $privateKey): string
    {
        try {
            $key = PublicKeyLoader::load($privateKey);
        } catch (\Throwable $e) {
            throw new InvalidKeyException('Invalid ECDSA private key.', 0, $e);
        }

        if (!$key instanceof EC\PrivateKey) {
            throw new InvalidKeyException('Not an ECDSA private key.');
        }

        /** @var EC\PrivateKey $configured */
        $configured = $key->withHash('sha256');

        $signature = $configured->sign($message);
        if (!\is_string($signature)) {
            throw new EncryptionException('ECDSA signing failed.');
        }

        return $signature;
    }

    #[\Override]
    public function verify(string $signature, string $message, string $publicKey): bool
    {
        try {
            $key = PublicKeyLoader::load($publicKey);
        } catch (\Throwable) {
            return false;
        }

        if (!$key instanceof EC\PublicKey) {
            return false;
        }

        try {
            /** @var EC\PublicKey $configured */
            $configured = $key->withHash('sha256');

            return (bool) $configured->verify($message, $signature);
        } catch (\Throwable) {
            return false;
        }
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return class_exists(EC::class);
    }

    #[\Override]
    public function algorithm(): string
    {
        return 'ecdsa-p256';
    }

    #[\Override]
    public function name(): string
    {
        return 'phpseclib';
    }
}

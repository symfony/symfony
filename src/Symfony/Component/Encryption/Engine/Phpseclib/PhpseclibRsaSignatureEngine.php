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

namespace Symfony\Component\Encryption\Engine\Phpseclib;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Symfony\Component\Encryption\Engine\SignatureEngineInterface;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * RSASSA-PKCS#1 v1.5 (SHA-256) signatures via phpseclib (the sodium/openssl-free
 * fallback). Output is byte-compatible with the OpenSSL engine.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class PhpseclibRsaSignatureEngine implements SignatureEngineInterface
{
    private const KEY_BITS = 3072;

    #[\Override]
    public function generateKeyPair(): array
    {
        $private = RSA::createKey(self::KEY_BITS);
        $public = $private->getPublicKey();
        if (!$public instanceof RSA\PublicKey) {
            throw new EncryptionException('RSA key generation failed.');
        }

        $publicPem = $public->toString('PKCS8');
        if (!\is_string($publicPem)) {
            throw new EncryptionException('RSA key serialisation failed.');
        }

        return [$publicPem, $private->toString('PKCS8')];
    }

    #[\Override]
    public function sign(string $message, string $privateKey): string
    {
        try {
            $key = PublicKeyLoader::load($privateKey);
        } catch (\Throwable $e) {
            throw new InvalidKeyException('Invalid RSA private key.', 0, $e);
        }

        if (!$key instanceof RSA\PrivateKey) {
            throw new InvalidKeyException('Not an RSA private key.');
        }

        /** @var RSA\PrivateKey $withPadding */
        $withPadding = $key->withPadding(RSA::SIGNATURE_PKCS1);
        /** @var RSA\PrivateKey $configured */
        $configured = $withPadding->withHash('sha256');

        return $configured->sign($message);
    }

    #[\Override]
    public function verify(string $signature, string $message, string $publicKey): bool
    {
        try {
            $key = PublicKeyLoader::load($publicKey);
        } catch (\Throwable) {
            return false;
        }

        if (!$key instanceof RSA\PublicKey) {
            return false;
        }

        try {
            /** @var RSA\PublicKey $withPadding */
            $withPadding = $key->withPadding(RSA::SIGNATURE_PKCS1);
            /** @var RSA\PublicKey $configured */
            $configured = $withPadding->withHash('sha256');

            return $configured->verify($message, $signature);
        } catch (\Throwable) {
            return false;
        }
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return class_exists(RSA::class);
    }

    #[\Override]
    public function algorithm(): string
    {
        return 'rsa';
    }

    #[\Override]
    public function name(): string
    {
        return 'phpseclib';
    }
}

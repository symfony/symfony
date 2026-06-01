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

use phpseclib3\Crypt\EC;
use Symfony\Component\Encryption\Engine\SignatureEngineInterface;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * Ed25519 detached signatures via phpseclib (the sodium-free fallback).
 *
 * Uses phpseclib's `libsodium` key format so keys and signatures are
 * byte-compatible with the sodium engine.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class PhpseclibEd25519Engine implements SignatureEngineInterface
{
    #[\Override]
    public function generateKeyPair(): array
    {
        $private = EC::createKey('Ed25519');
        $public = $private->getPublicKey();

        if (!$public instanceof EC\PublicKey) {
            throw new EncryptionException('Ed25519 key generation failed.');
        }

        return [
            $public->toString('libsodium'),
            $private->toString('libsodium'),
        ];
    }

    #[\Override]
    public function sign(string $message, string $privateKey): string
    {
        try {
            $key = EC::loadPrivateKeyFormat('libsodium', $privateKey);
        } catch (\Throwable $e) {
            throw new InvalidKeyException('Invalid Ed25519 secret key.', 0, $e);
        }

        if (!$key instanceof EC\PrivateKey) {
            throw new InvalidKeyException('Not an Ed25519 secret key.');
        }

        $signature = $key->sign($message);

        if (!\is_string($signature)) {
            throw new EncryptionException('Ed25519 signing failed.');
        }

        return $signature;
    }

    #[\Override]
    public function verify(string $signature, string $message, string $publicKey): bool
    {
        try {
            $key = EC::loadFormat('libsodium', $publicKey);
        } catch (\Throwable) {
            return false;
        }

        if (!$key instanceof EC\PublicKey) {
            return false;
        }

        try {
            return (bool) $key->verify($message, $signature);
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
        return 'ed25519';
    }

    #[\Override]
    public function name(): string
    {
        return 'phpseclib';
    }
}

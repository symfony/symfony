<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine\Sodium;

use Symfony\Component\Encryption\Engine\SignatureEngineInterface;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * Ed25519 detached signatures via libsodium.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class SodiumEd25519Engine implements SignatureEngineInterface
{
    private const PUBLIC_KEY_BYTES = 32;
    private const SECRET_KEY_BYTES = 64;
    private const SIGNATURE_BYTES = 64;

    #[\Override]
    public function generateKeyPair(): array
    {
        $keypair = sodium_crypto_sign_keypair();

        return [
            sodium_crypto_sign_publickey($keypair),
            sodium_crypto_sign_secretkey($keypair),
        ];
    }

    #[\Override]
    public function sign(string $message, string $privateKey): string
    {
        if (self::SECRET_KEY_BYTES !== \strlen($privateKey)) {
            throw new InvalidKeyException(\sprintf('Ed25519 secret key must be %d bytes.', self::SECRET_KEY_BYTES));
        }

        return sodium_crypto_sign_detached($message, $privateKey);
    }

    #[\Override]
    public function verify(string $signature, string $message, string $publicKey): bool
    {
        if (self::SIGNATURE_BYTES !== \strlen($signature) || self::PUBLIC_KEY_BYTES !== \strlen($publicKey)) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return \function_exists('sodium_crypto_sign_detached');
    }

    #[\Override]
    public function algorithm(): string
    {
        return 'ed25519';
    }

    #[\Override]
    public function name(): string
    {
        return 'sodium';
    }
}

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

use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * A self-describing asymmetric private key.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class PrivateKey implements KeyInterface
{
    use AsymmetricKeyTrait;

    private const MAGIC = 'SYR';

    public static function fromBytes(string $algorithm, string $purpose, string $bytes): self
    {
        return new self($algorithm, $purpose, $bytes);
    }

    public static function import(string $exported): self
    {
        [$algorithm, $purpose, $bytes] = self::parse($exported);

        return new self($algorithm, $purpose, $bytes);
    }

    private function ed25519PublicKeyBytes(): string
    {
        if (64 !== \strlen($this->bytes)) {
            throw new InvalidKeyException('An Ed25519 secret key must be 64 bytes.');
        }

        return substr($this->bytes, 32);
    }

    /**
     * Derive the matching public key.
     */
    public function derivePublic(): PublicKey
    {
        return match ($this->algorithm) {
            'x25519' => PublicKey::fromBytes(
                $this->algorithm,
                $this->purpose,
                sodium_crypto_box_publickey_from_secretkey($this->bytes),
            ),
            'rsa' => PublicKey::fromBytes(
                $this->algorithm,
                $this->purpose,
                RsaKeySupport::publicPemFromPrivatePem($this->bytes),
            ),
            'ed25519' => PublicKey::fromBytes(
                $this->algorithm,
                $this->purpose,
                $this->ed25519PublicKeyBytes(),
            ),
            'ecdsa-p256' => PublicKey::fromBytes(
                $this->algorithm,
                $this->purpose,
                EcKeySupport::publicPemFromPrivatePem($this->bytes),
            ),
            default => throw new InvalidKeyException(\sprintf('Cannot derive a public key for algorithm "%s".', $this->algorithm)),
        };
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Local;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;
use Symfony\Component\KeyManagement\KeyLoader\KeyLoaderInterface;

/**
 * Local libsodium-backed asymmetric KMS using anonymous public-key encryption
 * (Curve25519 + XSalsa20-Poly1305 via `sodium_crypto_box_seal`).
 *
 * Enables the "Symfony Secrets" pattern in self-hosted setups: deployments
 * that load only the public key can encrypt new payloads (devs commit
 * encrypted blobs to git) but cannot decrypt anything; deployments that
 * load the keypair can decrypt at runtime.
 *
 * Each entry returned by the {@see KeyLoaderInterface} must be either:
 *   - a 32-byte public key (encrypt-only mode), or
 *   - a 64-byte keypair (sk || pk, the libsodium convention) for full mode.
 *
 * Generate keypairs with:
 *
 *     $keypair = sodium_crypto_box_keypair();              // 64 bytes
 *     $publicKey = sodium_crypto_box_publickey($keypair);  // 32 bytes
 *
 * AAD is not supported by `crypto_box_seal` and a non-empty value triggers
 * {@see UnsupportedOperationException}. Callers needing AAD should use
 * {@see SodiumKms} (symmetric) or a cloud bridge.
 *
 * On the decrypt path, both `KeyNotFoundException` and the public-key-only
 * case are masked as {@see DecryptionFailedException} (key-enumeration
 * oracle hardening, and consistency with the rest of the component).
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class SealedBoxKms implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    public function __construct(private readonly KeyLoaderInterface $keys)
    {
        if (!\extension_loaded('sodium')) {
            throw new LogicException('The "sodium" PHP extension is required to use the SealedBoxKms backend.');
        }
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        if ('' !== $aad) {
            throw new UnsupportedOperationException('SealedBoxKms does not support additional authenticated data.');
        }
        if ($deterministic) {
            throw new UnsupportedOperationException('SealedBoxKms cannot produce deterministic ciphertexts: crypto_box_seal generates a fresh ephemeral keypair on every call.');
        }

        return new Ciphertext(sodium_crypto_box_seal($plaintext, $this->loadPublicKey($keyId)), $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        if ('' !== $aad) {
            throw new UnsupportedOperationException('SealedBoxKms does not support additional authenticated data.');
        }

        try {
            $material = $this->keys->load($ciphertext->keyId);
        } catch (KeyNotFoundException) {
            throw new DecryptionFailedException();
        }

        if (\SODIUM_CRYPTO_BOX_KEYPAIRBYTES !== \strlen($material)) {
            throw new DecryptionFailedException();
        }

        $plaintext = sodium_crypto_box_seal_open($ciphertext->blob, $material);
        if (false === $plaintext) {
            throw new DecryptionFailedException();
        }

        return $plaintext;
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        if ($length < 16) {
            throw new InvalidArgumentException(\sprintf('Data key length must be at least 16 bytes, %d given.', $length));
        }

        $plaintext = random_bytes($length);

        return new DataKey($plaintext, $this->encrypt($keyId, $plaintext, $aad));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }

    private function loadPublicKey(string $keyId): string
    {
        $material = $this->keys->load($keyId);

        return match (\strlen($material)) {
            \SODIUM_CRYPTO_BOX_PUBLICKEYBYTES => $material,
            \SODIUM_CRYPTO_BOX_KEYPAIRBYTES => sodium_crypto_box_publickey($material),
            default => throw new InvalidArgumentException(\sprintf('Key "%s" must be either a %d-byte public key or a %d-byte keypair, %d bytes given.', $keyId, \SODIUM_CRYPTO_BOX_PUBLICKEYBYTES, \SODIUM_CRYPTO_BOX_KEYPAIRBYTES, \strlen($material))),
        };
    }
}

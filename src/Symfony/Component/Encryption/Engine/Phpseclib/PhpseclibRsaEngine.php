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

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Symfony\Component\Encryption\Engine\RsaEngineInterface;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * RSA key generation and RSA-OAEP (SHA-256) key wrapping via phpseclib.
 *
 * Key generation prefers ext-openssl (fast) and falls back to phpseclib; the
 * OAEP wrap/unwrap always run through phpseclib so the hash is SHA-256
 * (PHP's openssl_* only supports SHA-1 OAEP).
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class PhpseclibRsaEngine implements RsaEngineInterface
{
    private const MIN_BITS = 2048;

    #[\Override]
    public function generateKeyPair(int $bits): array
    {
        if ($bits < self::MIN_BITS) {
            throw new InvalidArgumentException(\sprintf('RSA key size must be at least %d bits.', self::MIN_BITS));
        }

        if (\extension_loaded('openssl')) {
            $resource = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
            if (false !== $resource && openssl_pkey_export($resource, $privatePem)) {
                $details = openssl_pkey_get_details($resource);
                if (false !== $details && isset($details['key']) && \is_string($details['key'])) {
                    return [$details['key'], $privatePem];
                }
            }
        }

        $private = RSA::createKey($bits);

        if (!$private instanceof RSA\PrivateKey) {
            throw new EncryptionException('RSA key generation failed.');
        }

        $public = $private->getPublicKey();
        if (!$public instanceof RSA\PublicKey) {
            throw new EncryptionException('RSA public key extraction failed.');
        }

        $publicPem = $public->toString('PKCS8');
        $privatePem = $private->toString('PKCS8');

        if (!\is_string($publicPem)) {
            throw new EncryptionException('RSA key serialisation failed.');
        }

        return [$publicPem, $privatePem];
    }

    #[\Override]
    public function wrap(string $plaintextKey, string $recipientPublicPem): string
    {
        $wrapped = $this->publicKey($recipientPublicPem)->encrypt($plaintextKey);
        if (!\is_string($wrapped)) {
            throw new EncryptionException('RSA wrap failed.');
        }

        return $wrapped;
    }

    #[\Override]
    public function unwrap(string $wrapped, string $recipientPrivatePem): string
    {
        $privateKey = $this->privateKey($recipientPrivatePem);

        try {
            $plaintext = $privateKey->decrypt($wrapped);
        } catch (\Throwable $e) {
            throw new DecryptionException('RSA unwrap failed: wrong key or corrupted ciphertext.', 0, $e);
        }

        if (!\is_string($plaintext)) {
            throw new DecryptionException('RSA unwrap failed: wrong key or corrupted ciphertext.');
        }

        return $plaintext;
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return class_exists(RSA::class);
    }

    #[\Override]
    public function name(): string
    {
        return 'phpseclib-rsa';
    }

    private function publicKey(string $pem): RSA\PublicKey
    {
        try {
            $key = PublicKeyLoader::load($pem);
        } catch (\Throwable $e) {
            throw new InvalidKeyException('Invalid RSA public key.', 0, $e);
        }

        if (!$key instanceof RSA\PublicKey) {
            throw new InvalidKeyException('Not an RSA public key.');
        }

        /** @var RSA\PublicKey $step1 */
        $step1 = $key->withPadding(RSA::ENCRYPTION_OAEP);
        /** @var RSA\PublicKey $step2 */
        $step2 = $step1->withHash('sha256');
        /** @var RSA\PublicKey $configured */
        $configured = $step2->withMGFHash('sha256');

        return $configured;
    }

    private function privateKey(string $pem): RSA\PrivateKey
    {
        try {
            $key = PublicKeyLoader::load($pem);
        } catch (\Throwable $e) {
            throw new InvalidKeyException('Invalid RSA private key.', 0, $e);
        }

        if (!$key instanceof RSA\PrivateKey) {
            throw new InvalidKeyException('Not an RSA private key.');
        }

        /** @var RSA\PrivateKey $step1 */
        $step1 = $key->withPadding(RSA::ENCRYPTION_OAEP);
        /** @var RSA\PrivateKey $step2 */
        $step2 = $step1->withHash('sha256');
        /** @var RSA\PrivateKey $configured */
        $configured = $step2->withMGFHash('sha256');

        return $configured;
    }
}

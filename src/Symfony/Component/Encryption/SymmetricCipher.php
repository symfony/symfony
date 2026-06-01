<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Envelope\Envelope;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\NoEngineAvailableException;
use Symfony\Component\Encryption\Key\KeyDerivation;
use Symfony\Component\Encryption\Key\SecretKey;

/**
 * Authenticated symmetric encryption (ChaCha20-Poly1305, IETF).
 *
 * The active backend (Sodium or OpenSSL) is selected automatically and hidden;
 * ciphertext is a self-describing, versioned envelope so a value encrypted in
 * one environment decrypts in another. Supports a raw {@see SecretKey} or a
 * password (key derived via Argon2id, or PBKDF2 when sodium is absent).
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class SymmetricCipher implements SymmetricCipherInterface
{
    /**
     * Bounds for key-derivation cost parameters read back from a ciphertext.
     *
     * The Envelope carries the KDF parameters chosen at encryption time, so a
     * crafted ciphertext can otherwise dictate an arbitrarily expensive
     * derivation at decryption time (a memory/CPU exhaustion vector). Values
     * outside these bounds are rejected before the KDF runs. The accepted
     * range still comfortably covers libsodium's INTERACTIVE and SENSITIVE
     * presets and our PBKDF2 default.
     */
    private const MIN_ARGON2_OPSLIMIT = 1;
    private const MAX_ARGON2_OPSLIMIT = 16;
    private const MIN_ARGON2_MEMLIMIT = 8192;           // 8 KiB, libsodium's minimum
    private const MAX_ARGON2_MEMLIMIT = 2147483648;     // 2 GiB
    private const MIN_PBKDF2_ITERATIONS = 1;
    private const MAX_PBKDF2_ITERATIONS = 10000000;     // 10 million

    public function __construct(
        private readonly EngineSelector $engines = new EngineSelector(),
    ) {
    }

    #[\Override]
    public function generateKey(): SecretKey
    {
        return SecretKey::generate();
    }

    #[\Override]
    public function encrypt(string $plaintext, SecretKey $key): string
    {
        $engine = $this->engines->symmetricEngine();
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);
        $ciphertext = $engine->encrypt($plaintext, $key->bytes(), $nonce);

        return Encoding::toBase64(Envelope::forRawKey($nonce, $ciphertext)->serialize());
    }

    #[\Override]
    public function decrypt(string $ciphertext, SecretKey $key): string
    {
        $envelope = $this->parse($ciphertext);
        if (Envelope::KDF_NONE !== $envelope->kdfId()) {
            throw new DecryptionException('This ciphertext was encrypted with a password, not a key.');
        }

        return $this->engines->symmetricEngine()->decrypt($envelope->ciphertext(), $key->bytes(), $envelope->nonce());
    }

    #[\Override]
    public function encryptWithPassword(string $plaintext, #[\SensitiveParameter] string $password): string
    {
        $engine = $this->engines->symmetricEngine();
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);
        $salt = random_bytes(Envelope::SALT_BYTES);

        if (Envelope::KDF_ARGON2ID === KeyDerivation::preferredKdfId()) {
            $ops = \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
            $mem = \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
            $key = KeyDerivation::deriveArgon2id($password, $salt, $ops, $mem);
            $ciphertext = $engine->encrypt($plaintext, $key, $nonce);
            $envelope = Envelope::forArgon2id($salt, $ops, $mem, $nonce, $ciphertext);
        } else {
            $iterations = KeyDerivation::DEFAULT_PBKDF2_ITERATIONS;
            $key = KeyDerivation::derivePbkdf2($password, $salt, $iterations);
            $ciphertext = $engine->encrypt($plaintext, $key, $nonce);
            $envelope = Envelope::forPbkdf2($salt, $iterations, $nonce, $ciphertext);
        }

        $this->wipe($key);

        return Encoding::toBase64($envelope->serialize());
    }

    #[\Override]
    public function decryptWithPassword(string $ciphertext, #[\SensitiveParameter] string $password): string
    {
        $envelope = $this->parse($ciphertext);

        $key = match ($envelope->kdfId()) {
            Envelope::KDF_ARGON2ID => $this->deriveArgon2idFromEnvelope($password, $envelope),
            Envelope::KDF_PBKDF2_SHA256 => $this->derivePbkdf2FromEnvelope($password, $envelope),
            default => throw new DecryptionException('This ciphertext was encrypted with a key, not a password.'),
        };

        try {
            return $this->engines->symmetricEngine()->decrypt($envelope->ciphertext(), $key, $envelope->nonce());
        } finally {
            $this->wipe($key);
        }
    }

    private function deriveArgon2idFromEnvelope(#[\SensitiveParameter] string $password, Envelope $envelope): string
    {
        $ops = $envelope->argon2OpsLimit();
        $mem = $envelope->argon2MemLimit();
        if (
            $ops < self::MIN_ARGON2_OPSLIMIT || $ops > self::MAX_ARGON2_OPSLIMIT
            || $mem < self::MIN_ARGON2_MEMLIMIT || $mem > self::MAX_ARGON2_MEMLIMIT
        ) {
            throw new DecryptionException('Malformed ciphertext: key-derivation parameters are out of range.');
        }

        try {
            return KeyDerivation::deriveArgon2id($password, $envelope->salt(), $ops, $mem);
        } catch (NoEngineAvailableException $e) {
            throw new DecryptionException('This ciphertext requires Argon2id, which needs the sodium extension.', 0, $e);
        }
    }

    private function derivePbkdf2FromEnvelope(#[\SensitiveParameter] string $password, Envelope $envelope): string
    {
        $iterations = $envelope->pbkdf2Iterations();
        if ($iterations < self::MIN_PBKDF2_ITERATIONS || $iterations > self::MAX_PBKDF2_ITERATIONS) {
            throw new DecryptionException('Malformed ciphertext: key-derivation parameters are out of range.');
        }

        return KeyDerivation::derivePbkdf2($password, $envelope->salt(), $iterations);
    }

    private function parse(string $ciphertext): Envelope
    {
        try {
            return Envelope::deserialize(Encoding::fromBase64($ciphertext));
        } catch (InvalidArgumentException $e) {
            throw new DecryptionException('Malformed ciphertext.', 0, $e);
        }
    }

    /**
     * @param-out string|null $key
     */
    private function wipe(string &$key): void
    {
        if (\function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }
}

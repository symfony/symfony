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

use Symfony\Component\Encryption\Exception\NoEngineAvailableException;

/**
 * Argon2id password hashing for credential storage and verification.
 *
 * Do NOT use this to derive symmetric encryption keys — use the symmetric
 * cipher's password mode for that.
 *
 * @author David Gebler <me@davegebler.com>
 */
final class PasswordHasher implements PasswordHasherInterface
{
    public const OPSLIMIT_INTERACTIVE = \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
    public const OPSLIMIT_MODERATE = \SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
    public const OPSLIMIT_SENSITIVE = \SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE;
    public const MEMLIMIT_INTERACTIVE = \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
    public const MEMLIMIT_MODERATE = \SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE;
    public const MEMLIMIT_SENSITIVE = \SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE;

    public function __construct(
        private readonly int $opsLimit = self::OPSLIMIT_MODERATE,
        private readonly int $memLimit = self::MEMLIMIT_MODERATE,
    ) {
        if (!\extension_loaded('sodium')) {
            throw new NoEngineAvailableException('PasswordHasher requires the sodium extension.');
        }
    }

    /**
     * Hash a password into an Argon2id verifier string (starts with $argon2id$)
     * that embeds the salt and parameters.
     */
    #[\Override]
    public function hash(string $password): string
    {
        return sodium_crypto_pwhash_str($password, $this->opsLimit, $this->memLimit);
    }

    /**
     * Verify a password against a stored hash. Returns false for both wrong
     * passwords and malformed hashes; never throws on mismatch.
     */
    #[\Override]
    public function verify(string $password, string $hash): bool
    {
        return sodium_crypto_pwhash_str_verify($hash, $password);
    }

    /**
     * True when the stored hash was computed with weaker parameters than this
     * hasher is configured with, or when the hash is not a recognised Argon2id
     * verifier string (e.g. it is malformed or empty).
     */
    #[\Override]
    public function needsRehash(string $hash): bool
    {
        return sodium_crypto_pwhash_str_needs_rehash($hash, $this->opsLimit, $this->memLimit);
    }
}

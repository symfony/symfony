<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Key;

use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Envelope\Envelope;
use Symfony\Component\Encryption\Exception\NoEngineAvailableException;

/**
 * Derives a 32-byte symmetric key from a password.
 *
 * Argon2id (via libsodium) is preferred when available; PBKDF2-HMAC-SHA256 is
 * the always-available portable fallback. The chosen KDF and its parameters are
 * recorded in the Envelope so decryption can re-derive the same key.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class KeyDerivation
{
    public const DEFAULT_PBKDF2_ITERATIONS = 600000;

    public static function preferredKdfId(): int
    {
        return \function_exists('sodium_crypto_pwhash')
            ? Envelope::KDF_ARGON2ID
            : Envelope::KDF_PBKDF2_SHA256;
    }

    public static function deriveArgon2id(string $password, string $salt, int $opsLimit, int $memLimit): string
    {
        if (!\function_exists('sodium_crypto_pwhash')) {
            throw new NoEngineAvailableException('Argon2id key derivation requires the sodium extension.');
        }

        return sodium_crypto_pwhash(
            SymmetricEngineInterface::KEY_BYTES,
            $password,
            $salt,
            $opsLimit,
            $memLimit,
            \SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    public static function derivePbkdf2(string $password, string $salt, int $iterations): string
    {
        return hash_pbkdf2('sha256', $password, $salt, $iterations, SymmetricEngineInterface::KEY_BYTES, true);
    }
}

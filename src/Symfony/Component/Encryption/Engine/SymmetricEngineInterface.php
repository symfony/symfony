<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine;

/**
 * Internal contract for an authenticated symmetric (AEAD) backend.
 *
 * Implementations produce and consume ChaCha20-Poly1305 (IETF) ciphertext with
 * the 16-byte Poly1305 tag appended, so output is interchangeable between
 * engines. Not part of the public API.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
interface SymmetricEngineInterface
{
    public const KEY_BYTES = 32;
    public const NONCE_BYTES = 12;
    public const TAG_BYTES = 16;

    /**
     * Encrypt plaintext; returns ciphertext with the 16-byte tag appended.
     */
    public function encrypt(string $plaintext, string $key, string $nonce): string;

    /**
     * Decrypt ciphertext-with-tag. Throws DecryptionException on authentication
     * failure or corrupted input.
     */
    public function decrypt(string $ciphertext, string $key, string $nonce): string;

    /**
     * True when this engine can run in the current PHP runtime.
     */
    public function isAvailable(): bool;

    /**
     * Stable short identifier ("sodium", "openssl"), for diagnostics/tests.
     */
    public function name(): string;
}

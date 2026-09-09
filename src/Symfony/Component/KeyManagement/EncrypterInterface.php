<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

/**
 * Encrypts data through a managed key.
 *
 * Implementations delegate the actual key material handling to a remote KMS
 * (AWS KMS, HashiCorp Vault Transit, ...) or a local backend (libsodium,
 * OpenSSL) for development and tests. The plaintext key material itself
 * never leaves the underlying KMS.
 *
 * Additional Authenticated Data (AAD), also known as "encryption context" in
 * some backends, is integrity-protected but not encrypted. It is treated as
 * opaque bytes: callers are responsible for any serialization they need
 * (e.g. canonical JSON for a structured map). The exact same bytes MUST be
 * supplied at decryption time. Backends that cannot enforce AAD MUST throw
 * {@see UnsupportedOperationException} when a non-empty AAD is provided
 * rather than silently ignore it.
 *
 * The `$deterministic` flag selects between two encryption modes:
 *   - random (default): a fresh nonce is drawn for every call, so the same
 *     plaintext encrypts to a different ciphertext each time. Use this for
 *     anything that does not need to be searched.
 *   - deterministic: the nonce is derived from the AAD and the plaintext
 *     keyed by the master key (typically HMAC-SHA512 truncated, or BLAKE2b
 *     keyed for libsodium), so the same (key, AAD, plaintext) triple always
 *     produces the same ciphertext. Enables exact-match indexing in
 *     databases. The AAD MUST take part in that derivation, and MUST do so
 *     unambiguously: two encryptions differing only by their AAD would
 *     otherwise reuse a nonce under the same key, which hands an attacker
 *     the authentication key of the AEAD. Backends that cannot offer this
 *     mode MUST throw {@see UnsupportedOperationException} when
 *     `$deterministic` is `true`.
 *
 * Most backends implement {@see DecrypterInterface} as well; some
 * deployments only expose the encrypting half (e.g. a write-only host that
 * holds only the public key of an asymmetric scheme).
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface EncrypterInterface
{
    /**
     * @throws KeyNotFoundException          If `$keyId` is unknown to the backend
     * @throws UnsupportedOperationException If `$aad` is non-empty and the backend cannot enforce it,
     *                                       or if `$deterministic` is true and the backend cannot offer it
     * @throws RuntimeException              On any other backend failure
     */
    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext;
}

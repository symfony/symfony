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
     * @throws UnsupportedOperationException If `$aad` is non-empty and the backend cannot enforce it
     * @throws RuntimeException              On any other backend failure
     */
    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = ''): Ciphertext;
}

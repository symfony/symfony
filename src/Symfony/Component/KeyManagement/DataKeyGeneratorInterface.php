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

use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

/**
 * Capability interface for KMS backends that produce envelope-encryption
 * data keys (DEKs). The DEK is returned both in plaintext (for local symmetric
 * encryption of large payloads) and wrapped by the master key (for
 * persistence). The master key never leaves the KMS.
 *
 * Backends expose this primitive in different ways. Concrete bridges may:
 *   - call a native one-shot endpoint (AWS KMS `GenerateDataKey`, Vault
 *     `transit/datakey/plaintext`);
 *   - generate the DEK locally with `random_bytes()` and round-trip it through
 *     the KMS using `wrapKey/unwrapKey` (Azure Key Vault, GCP Cloud KMS) or
 *     plain `encrypt` (this is what {@see Local\SodiumKms} does);
 *   - decline support and not implement this interface, in which case callers
 *     detect the capability with `instanceof`.
 *
 * Supported `$length` values vary by bridge (HashiCorp Vault Transit only
 * accepts 16/32/64 bytes; AWS KMS, Azure Key Vault, Google Cloud KMS and the
 * local backends accept any value of at least 16 bytes). The intersection
 * that round-trips through every shipped bridge is `{16, 32, 64}`.
 * {@see EnvelopeEncrypter} sticks to the `SelfContainedFormat` defaults (32 bytes
 * for V1) so users only hit bridge-specific limits when they call
 * `generateDataKey()` themselves with a custom length.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface DataKeyGeneratorInterface
{
    /**
     * @param positive-int $length Length of the data key in bytes; backend-specific minimum
     *                             (e.g. 16 for AES-128). Each implementation throws
     *                             `InvalidArgumentException` if the requested length is
     *                             outside its accepted range. See the class-level docblock
     *                             for the cross-backend `{16, 32, 64}` intersection.
     * @param string       $aad    Bound to the wrapped form, must match on unwrap
     *
     * @throws KeyNotFoundException          If `$keyId` is unknown to the backend
     * @throws UnsupportedOperationException If the backend cannot generate data keys
     * @throws RuntimeException              On any other backend failure
     */
    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey;

    /**
     * Inverse of {@see generateDataKey()}: unwraps a previously-wrapped DEK and
     * returns it as a {@see DataKey}, so the recovered plaintext is subject to
     * the same auto-wipe semantics as a freshly generated one.
     *
     * @param string $aad Must match the AAD supplied at wrap time
     *
     * @throws KeyNotFoundException      If the wrapped ciphertext's key is unknown
     * @throws DecryptionFailedException If unwrapping fails (tampering, wrong key, AAD mismatch)
     */
    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey;
}

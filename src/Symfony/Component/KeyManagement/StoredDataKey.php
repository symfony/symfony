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

/**
 * A data key as a store persists it: wrapped, never in plaintext.
 *
 * This is the shape the rewrapping path works on. {@see RewrappableDataKeyStoreInterface} lists
 * stored keys so that each one can be unwrapped with the client that produced it and re-wrapped
 * with another, which is how a master key or a whole KMS provider is rotated without reading or
 * rewriting a single encrypted payload: only these rows change.
 *
 * `$client` is the name of the configured KMS client that wrapped the key, and the master key
 * itself is named by `$wrapped->keyId`. Both are needed because a migration runs with the old and
 * the new provider configured at the same time, so each row must say who can unwrap it.
 *
 * `$binding` ties the key to its reference and scope, {@see bindingFor()}.
 * It is keyed by the data key, so only what can open the key can produce it, and a store checks it once the key is open.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class StoredDataKey
{
    /**
     * Tells this derivation from any other use of the same data key, and leaves room for a second.
     */
    private const string BINDING_INFO = 'symfony/key-management/data-key-binding/v1';

    public function __construct(
        public readonly string $reference,
        public readonly string $scope,
        public readonly Ciphertext $wrapped,
        public readonly string $client,
        public readonly string $binding,
    ) {
    }

    /**
     * Proof that a data key was minted for `$reference` and `$scope`, which only its plaintext can produce.
     *
     * The wrapping of a data key is bound to nothing but the master key.
     * Without this proof, a row whose scope was edited would hand the key of one tenant, column or purpose to another one, and the row of a retired key copied under a fresh UUIDv7 would read as the current key of its scope.
     * The length prefix keeps two different pairs from hashing the same input.
     *
     * The HMAC key is derived from the data key, instead of passing authenticated data to the KMS, which some backends cannot enforce.
     * Forging a binding thus takes the KMS, not only write access to the table, and a rewrap keeps it valid since the key does not change.
     */
    public static function bindingFor(string $reference, string $scope, #[\SensitiveParameter] string $plaintext): string
    {
        return hash_hmac('sha256', pack('n', \strlen($reference)).$reference.$scope, hash_hkdf('sha256', $plaintext, 32, self::BINDING_INFO), true);
    }
}

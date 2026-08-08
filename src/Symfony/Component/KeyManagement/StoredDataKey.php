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
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class StoredDataKey
{
    public function __construct(
        public readonly string $reference,
        public readonly string $scope,
        public readonly Ciphertext $wrapped,
        public readonly string $client,
    ) {
    }
}

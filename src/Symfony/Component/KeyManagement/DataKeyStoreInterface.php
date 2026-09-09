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

use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Persists wrapped data keys and hands them back usable.
 *
 * The store inverts the trade-off of a self-contained {@see Envelope}: instead of carrying its
 * wrapped data key, each payload carries a reference to a stored one. Two things follow. The KMS
 * is contacted once per data key and per process rather than once per encrypted value, and the
 * master key that protects the data keys can be rotated, or swapped for another provider, by
 * rewrapping a handful of rows instead of rewriting every payload.
 *
 * A scope is the unit a data key is shared over: whatever the application decides that equal
 * scopes may share a key. A column identifier, a tenant, a purpose. Values encrypted under the
 * same scope reuse the same key until it is retired, and each retirement only affects payloads
 * written after it, since older payloads keep referring to the key they were written with.
 *
 * Retirement is deliberately left out of this contract. Implementations are free to retire a key
 * by age, by usage, or never, and {@see current()} is where that decision surfaces. Do bear in
 * mind why it matters: with the random 96-bit nonces of AES-256-GCM, NIST caps a single key at
 * 2^32 encryptions before nonce collisions stop being negligible.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface DataKeyStoreInterface
{
    /**
     * Returns the data key to encrypt with in `$scope`, creating one when the scope has none yet
     * or when the implementation considers the current one retired.
     *
     * @throws RuntimeException If the key cannot be created or unwrapped
     */
    public function current(string $scope): DataKeyHandle;

    /**
     * Returns the data key a previously written payload refers to.
     *
     * @throws DataKeyNotFoundException  If no stored key matches `$reference`
     * @throws DecryptionFailedException If the stored key cannot be unwrapped
     */
    public function get(string $reference): DataKeyHandle;
}

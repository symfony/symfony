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

/**
 * A {@see DataKeyStoreInterface} whose stored keys can be enumerated and re-wrapped.
 *
 * This is the administration half, kept apart so that the encryption path only ever sees
 * {@see DataKeyStoreInterface}. It exists for one scenario: moving the protection of every data
 * key from one master key, or one provider, to another while the application keeps running. The
 * old and the new client are both configured, {@see all()} walks the rows, each one is unwrapped
 * with the client it records and re-wrapped with the target one, and {@see rewrap()} writes it
 * back. No encrypted payload is read or rewritten.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface RewrappableDataKeyStoreInterface extends DataKeyStoreInterface
{
    /**
     * Walks the stored keys, oldest first, optionally restricted to those wrapped by `$client`.
     *
     * @return iterable<StoredDataKey>
     */
    public function all(?string $client = null): iterable;

    /**
     * Replaces the wrapping of a stored key.
     *
     * `$rewrapped` contains the data key of `$row` and its new wrapping.
     * A data key that doesn't match the binding of the row is refused, {@see StoredDataKey::$binding}.
     * Otherwise, the store computes the binding again, which is how it moves rows to a new secret.
     * The reference and the scope stay the same, so payloads referring to the key keep resolving.
     *
     * @throws DataKeyNotFoundException  If no stored key matches `$row`
     * @throws DecryptionFailedException If `$rewrapped` doesn't contain the data key `$row` is bound to
     */
    public function rewrap(StoredDataKey $row, DataKey $rewrapped, string $client): void;

    /**
     * The authenticated data this store wraps `$row` with, empty when its backend authenticates nothing.
     *
     * A rewrap happens outside the store, so it has to bind what the store binds,
     * {@see StoredDataKey::wrappingContextFor()}, or leave a row the store can no longer open.
     */
    public function wrappingContextOf(StoredDataKey $row): string;

    /**
     * Retires the current key of `$scope` by creating a fresh one, which becomes current.
     *
     * Payloads already written keep referring to the retired key, so nothing has to be rewritten.
     */
    public function rotate(string $scope): DataKeyHandle;
}

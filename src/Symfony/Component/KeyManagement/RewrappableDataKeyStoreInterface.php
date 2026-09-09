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
     * Replaces the wrapping of a stored key, leaving its reference and its scope untouched so that
     * payloads referring to it keep resolving.
     *
     * @throws DataKeyNotFoundException If no stored key matches `$reference`
     */
    public function rewrap(string $reference, Ciphertext $wrapped, string $client): void;

    /**
     * Retires the current key of `$scope` by creating a fresh one, which becomes current.
     *
     * Payloads already written keep referring to the retired key, so nothing has to be rewritten.
     */
    public function rotate(string $scope): DataKeyHandle;
}

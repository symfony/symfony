<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;

/**
 * Keeps the state of locks, each of them owned by the Key that acquired it.
 *
 * Only the owning key can extend or release a lock. Sharing a lock with another
 * process requires serializing its key and passing it to that process.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface PersistingStoreInterface
{
    /**
     * Stores the resource if it's not locked by someone else.
     *
     * @return void
     *
     * @throws LockAcquiringException
     * @throws LockConflictedException
     */
    public function save(Key $key);

    /**
     * Removes the resource from the storage if the given key owns it.
     *
     * Does nothing when the lock is owned by another key.
     *
     * @return void
     *
     * @throws LockReleasingException
     */
    public function delete(Key $key);

    /**
     * Returns whether or not the given key owns the lock on the resource.
     */
    public function exists(Key $key): bool;

    /**
     * Extends the TTL of a resource.
     *
     * @param float $ttl amount of seconds to keep the lock in the store
     *
     * @return void
     *
     * @throws LockConflictedException
     */
    public function putOffExpiration(Key $key, float $ttl);
}

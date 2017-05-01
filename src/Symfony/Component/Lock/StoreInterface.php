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

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\NotSupportedException;

/**
 * StoreInterface defines an interface to manipulate a lock store.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface StoreInterface
{
    /**
     * Stores the resource if it's not locked by someone else.
     *
     * @param Key $key key to lock
     *
     * @throws LockConflictedException
     */
    public function save(Key $key);

    /**
     * Waits a key becomes free, then stores the resource.
     *
     * If the store does not support this feature it should throw a NotSupportedException.
     *
     * @param Key $key key to lock
     *
     * @throws LockConflictedException
     * @throws NotSupportedException
     */
    public function waitAndSave(Key $key);

    /**
     * Extends the ttl of a resource.
     *
     * If the store does not support this feature it should throw a NotSupportedException.
     *
     * @param Key   $key key to lock
     * @param float $ttl amount of second to keep the lock in the store
     *
     * @throws LockConflictedException
     * @throws NotSupportedException
     */
    public function putOffExpiration(Key $key, $ttl);

    /**
     * Removes a resource from the storage.
     *
     * @param Key $key key to remove
     */
    public function delete(Key $key);

    /**
     * Returns whether or not the resource exists in the storage.
     *
     * @param Key $key key to remove
     *
     * @return bool
     */
    public function exists(Key $key);
}

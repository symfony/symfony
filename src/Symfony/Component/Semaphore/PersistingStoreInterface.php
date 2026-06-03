<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore;

use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreReleasingException;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface PersistingStoreInterface
{
    /**
     * Stores the resource if the semaphore is not full.
     *
     * @throws SemaphoreAcquiringException
     */
    public function save(Key $key, float $ttlInSecond): void;

    /**
     * Removes a resource from the storage.
     *
     * @throws SemaphoreReleasingException
     */
    public function delete(Key $key): void;

    /**
     * Returns whether or not the resource exists in the storage.
     *
     * Implementations may have side effects on the {@see Key}'s opaque state
     * (e.g. dropping references to slots that were lost on the backend) and
     * may issue more than one backend round-trip. Callers in hot paths or
     * destructors should expect O(weight) cost on stores that track each
     * slot individually (see {@see Store\LockStore})
     * versus O(1) on stores that maintain a single token per key (see
     * {@see Store\RedisStore}).
     */
    public function exists(Key $key): bool;

    /**
     * Extends the TTL of a resource.
     *
     * @throws SemaphoreExpiredException
     */
    public function putOffExpiration(Key $key, float $ttlInSecond): void;
}

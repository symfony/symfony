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
 * LockInterface defines an interface to manipulate the status of a lock.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface LockInterface
{
    /**
     * Acquires the lock. If the lock is acquired by someone else, the parameter `blocking` determines whether or not
     * the call should block until the release of the lock.
     *
     * @throws LockConflictedException If the lock is acquired by someone else in blocking mode
     * @throws LockAcquiringException  If the lock cannot be acquired
     */
    public function acquire(bool $blocking = false): bool;

    /**
     * Increase the duration of an acquired lock.
     *
     * @param float|null $ttl Maximum expected lock duration in seconds
     *
     * @return void
     *
     * @throws LockConflictedException If the lock is acquired by someone else
     * @throws LockAcquiringException  If the lock cannot be refreshed
     */
    public function refresh(?float $ttl = null);

    /**
     * Returns whether or not the lock is acquired.
     */
    public function isAcquired(): bool;

    /**
     * Release the lock.
     *
     * @return void
     *
     * @throws LockReleasingException If the lock cannot be released
     */
    public function release();

    public function isExpired(): bool;

    /**
     * Returns the remaining lifetime in seconds.
     */
    public function getRemainingLifetime(): ?float;
}

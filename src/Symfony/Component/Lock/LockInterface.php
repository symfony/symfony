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
     * the the call should block until the release of the lock.
     *
     * @param bool $blocking Whether or not the Lock should wait for the release of someone else
     *
     * @return bool Whether or not the lock had been acquired.
     *
     * @throws LockConflictedException If the lock is acquired by someone else in blocking mode
     * @throws LockAcquiringException  If the lock can not be acquired
     */
    public function acquire($blocking = false);

    /**
     * Increase the duration of an acquired lock.
     *
     * @throws LockConflictedException If the lock is acquired by someone else
     * @throws LockAcquiringException  If the lock can not be refreshed
     */
    public function refresh();

    /**
     * Returns whether or not the lock is acquired.
     *
     * @return bool
     */
    public function isAcquired();

    /**
     * Release the lock.
     *
     * @throws LockReleasingException If the lock can not be released
     */
    public function release();
}

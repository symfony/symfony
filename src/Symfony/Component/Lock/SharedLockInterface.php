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

/**
 * SharedLockInterface defines an interface to manipulate the status of a shared lock.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface SharedLockInterface extends LockInterface
{
    /**
     * Acquires the lock for reading. If the lock is acquired by someone else in write mode, the parameter `blocking`
     * determines whether or not the call should block until the release of the lock.
     *
     * @return bool
     *
     * @throws LockConflictedException If the lock is acquired by someone else in blocking mode
     * @throws LockAcquiringException  If the lock cannot be acquired
     */
    public function acquireRead(bool $blocking = false);
}

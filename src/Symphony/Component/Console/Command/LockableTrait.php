<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Console\Command;

use Symphony\Component\Console\Exception\LogicException;
use Symphony\Component\Console\Exception\RuntimeException;
use Symphony\Component\Lock\Factory;
use Symphony\Component\Lock\Lock;
use Symphony\Component\Lock\Store\FlockStore;
use Symphony\Component\Lock\Store\SemaphoreStore;

/**
 * Basic lock feature for commands.
 *
 * @author Geoffrey Brier <geoffrey.brier@gmail.com>
 */
trait LockableTrait
{
    /** @var Lock */
    private $lock;

    /**
     * Locks a command.
     *
     * @return bool
     */
    private function lock($name = null, $blocking = false)
    {
        if (!class_exists(SemaphoreStore::class)) {
            throw new RuntimeException('To enable the locking feature you must install the symphony/lock component.');
        }

        if (null !== $this->lock) {
            throw new LogicException('A lock is already in place.');
        }

        if (SemaphoreStore::isSupported()) {
            $store = new SemaphoreStore();
        } else {
            $store = new FlockStore();
        }

        $this->lock = (new Factory($store))->createLock($name ?: $this->getName());
        if (!$this->lock->acquire($blocking)) {
            $this->lock = null;

            return false;
        }

        return true;
    }

    /**
     * Releases the command lock if there is one.
     */
    private function release()
    {
        if ($this->lock) {
            $this->lock->release();
            $this->lock = null;
        }
    }
}

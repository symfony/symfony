<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\StoreInterface;

/**
 * Basic lock feature for commands.
 *
 * @author Geoffrey Brier <geoffrey.brier@gmail.com>
 */
trait LockableTrait
{
    /**
     * @var LockInterface
     */
    private $lock;

    /**
     * @var StoreInterface
     */
    private $store;

    /**
     * Locks a command.
     *
     * @return bool
     */
    private function lock($name = null, $blocking = false)
    {
        if (!class_exists(SemaphoreStore::class)) {
            throw new LogicException('To enable the locking feature you must install the symfony/lock component.');
        }

        if (null !== $this->lock) {
            throw new LogicException('A lock is already in place.');
        }

        if (null === $this->store) {
            if (SemaphoreStore::isSupported()) {
                $this->store = new SemaphoreStore();
            } else {
                $this->store = new FlockStore();
            }
        }

        $this->lock = (new Factory($this->store))->createLock($name ?: $this->getName());
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

    /**
     * Sets the internal store for the command to be used.
     */
    public function setStore(StoreInterface $store)
    {
        $this->store = $store;
    }
}

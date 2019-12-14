<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Worker;

use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerLock implements WorkerLockInterface
{
    private $store;

    /**
     * @param BlockingStoreInterface|PersistingStoreInterface|null $store
     */
    public function __construct($store = null)
    {
        $this->store = $store;
    }

    public function getLock(TaskInterface $task): LockInterface
    {
        if (null === $this->store) {
            $this->store = new FlockStore();
        }

        $factory = new LockFactory($this->store);

        return $factory->createLock($task->getName());
    }
}

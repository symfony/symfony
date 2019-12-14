<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Worker;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\WorkerLock;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerLockTest extends TestCase
{
    public function testLockCanBeCreatedWhenNull(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $workerLock = new WorkerLock();
        static::assertInstanceOf(LockInterface::class, $workerLock->getLock($task));
    }

    public function testLockCanBeCreatedWithBlockingStore(): void
    {
        $lock = $this->createMock(BlockingStoreInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $workerLock = new WorkerLock($lock);
        static::assertInstanceOf(LockInterface::class, $workerLock->getLock($task));
    }

    public function testLockCanBeCreatedWithPersistingStore(): void
    {
        $lock = $this->createMock(PersistingStoreInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $workerLock = new WorkerLock($lock);
        static::assertInstanceOf(LockInterface::class, $workerLock->getLock($task));
    }
}

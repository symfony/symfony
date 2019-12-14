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
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\TraceableWorker;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TraceableWorkerTest extends TestCase
{
    public function testExecutedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(true);

        $traceableWorker = new TraceableWorker($worker);
        $traceableWorker->execute($task);

        static::assertTrue($traceableWorker->isRunning());
        static::assertArrayHasKey('foo', $traceableWorker->getExecutedTasks());
        static::assertSame($task, $traceableWorker->getExecutedTasks()['foo']);
    }
}

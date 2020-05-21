<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\DataCollector\SchedulerDataCollector;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\TraceableScheduler;
use Symfony\Component\Scheduler\Transport\TraceableTransport;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDataCollectorTest extends TestCase
{
    public function testSchedulerDataCollectorIsValid(): void
    {
        static::assertSame('scheduler', (new SchedulerDataCollector())->getName());
    }

    public function testSchedulerCanCollectData(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($taskList);
        $scheduler->expects(self::exactly(2))->method('getTasks')->willReturn($taskList);
        $scheduler->expects(self::once())->method('getTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));

        $transport = $this->createMock(TransportInterface::class);

        $worker = $this->createMock(WorkerInterface::class);

        $dataCollector = new SchedulerDataCollector();
        $traceableScheduler = new TraceableScheduler($scheduler);
        $traceableTransport = new TraceableTransport($transport);

        $dataCollector->registerScheduler('foo', $traceableScheduler);
        $dataCollector->registerTransport('foo', $traceableTransport);
        $traceableScheduler->schedule(new ShellTask('foo', 'echo Symfony'));

        $dataCollector->lateCollect();

        static::assertNotEmpty($dataCollector->getScheduledTasks());
        static::assertNotEmpty($dataCollector->getSchedulers());
        static::assertArrayHasKey('foo', $dataCollector->getSchedulers());
        static::assertNotEmpty($dataCollector->getScheduledTasksByScheduler('foo'));
        static::assertCount(1, $dataCollector->getScheduledTasksByScheduler('foo'));

        static::assertNotEmpty($dataCollector->getTransports());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Command;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Command\ConsumeTasksCommand;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConsumeTasksCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $command = new ConsumeTasksCommand($eventDispatcher, $schedulerRegistry, $workerRegistry, $logger);

        static::assertSame('scheduler:consume', $command->getName());
        static::assertSame('Consumes tasks', $command->getDescription());
        static::assertNotNull($command->getDefinition());
    }

    public function testCommandCannotConsumeWithoutAvailableWorker(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::once())->method('filter')->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);

        $command = new ConsumeTasksCommand($eventDispatcher, $schedulerRegistry, $workerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('No worker is available, please retry', $tester->getDisplay());
    }

    public function testCommandCannotConsumeEmptySchedulers(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('addSubscriber');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('filter')->willReturn([]);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::once())->method('filter')->willReturn([$worker]);

        $logger = $this->createMock(LoggerInterface::class);

        $command = new ConsumeTasksCommand($eventDispatcher, $schedulerRegistry, $workerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('No schedulers can be found, please retry', $tester->getDisplay());
    }

    public function testCommandCannotConsumeInconsistentSchedulers(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('addSubscriber');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $secondScheduler = $this->createMock(SchedulerInterface::class);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('filter')->willReturn([$scheduler, $secondScheduler]);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::once())->method('filter')->willReturn([$worker]);

        $logger = $this->createMock(LoggerInterface::class);

        $command = new ConsumeTasksCommand($eventDispatcher, $schedulerRegistry, $workerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('The schedulers cannot be found, please retry', $tester->getDisplay());
    }

    public function testCommandCanConsumeDefinedSchedulers(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('addSubscriber');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('filter')->willReturn([$scheduler]);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::once())->method('filter')->willReturn([$worker]);

        $logger = $this->createMock(LoggerInterface::class);

        $command = new ConsumeTasksCommand($eventDispatcher, $schedulerRegistry, $workerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('Consuming tasks from scheduler: "foo"', $tester->getDisplay());
        static::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
    }

    public function testCommandCanConsumeSchedulersWithTaskLimit(): void
    {
    }

    public function testCommandCanConsumeSchedulersWithTimeLimit(): void
    {
    }
}

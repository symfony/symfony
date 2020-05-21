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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Scheduler\Command\ConsumeTasksCommand;
use Symfony\Component\Scheduler\Runner\RunnerInterface;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcherInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
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
        $logger = $this->createMock(LoggerInterface::class);
        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);

        $command = new ConsumeTasksCommand([], $watcher, $eventDispatcher, $schedulerRegistry, $logger);

        static::assertSame('scheduler:consume', $command->getName());
        static::assertSame('Consumes tasks', $command->getDescription());
        static::assertNotNull($command->getDefinition());
    }

    public function testCommandCannotConsumeEmptySchedulers(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('filter')->willReturn([]);

        $command = new ConsumeTasksCommand([], $watcher, $eventDispatcher, $schedulerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('No schedulers can be found, please retry', $tester->getDisplay());
    }

    public function testCommandCannotConsumeInconsistentSchedulers(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $secondScheduler = $this->createMock(SchedulerInterface::class);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('filter')->willReturn([$scheduler, $secondScheduler]);

        $command = new ConsumeTasksCommand([], $watcher, $eventDispatcher, $schedulerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('The schedulers cannot be found, please retry', $tester->getDisplay());
    }

    public function testCommandCanConsumeDefinedSchedulers(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('filter')->willReturn([$scheduler]);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);

        $command = new ConsumeTasksCommand([$runner], $watcher, $eventDispatcher, $schedulerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('Consuming tasks from scheduler: "foo"', $tester->getDisplay());
        static::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
    }

    public function testCommandCanConsumeSchedulersWithTaskLimit(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('filter')->willReturn([$scheduler]);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);

        $command = new ConsumeTasksCommand([$runner], $watcher, $eventDispatcher, $schedulerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
            '--limit' => 10,
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('The worker will automatically exit once 10 tasks has been processed', $tester->getDisplay());
        static::assertStringContainsString('Consuming tasks from scheduler: "foo"', $tester->getDisplay());
        static::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
    }

    public function testCommandCanConsumeSchedulersWithTimeLimit(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('filter')->willReturn([$scheduler]);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);

        $command = new ConsumeTasksCommand([$runner], $watcher, $eventDispatcher, $schedulerRegistry, $logger);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            'schedulers' => ['foo'],
            '--time-limit' => 10,
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('The worker will automatically exit once it has been running for 10 seconds', $tester->getDisplay());
        static::assertStringContainsString('Consuming tasks from scheduler: "foo"', $tester->getDisplay());
        static::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
    }
}

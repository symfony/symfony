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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Command\ListScheduledTaskCommand;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GetScheduledTaskCommentTest extends TestCase
{
    public function testCommandIsCorrectlyConfigured(): void
    {
        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $command = new ListScheduledTaskCommand($schedulerRegistry);

        static::assertSame('scheduler:get-tasks', $command->getName());
        static::assertSame('List the scheduled tasks', $command->getDescription());
    }

    public function testCommandCannotReturnTaskOnEmptyScheduler(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->willReturn($scheduler);

        $command = new ListScheduledTaskCommand($schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:get-tasks'));
        $tester->execute([
            'scheduler' => 'foo',
            '--expression' => '* * * * *',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithoutFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('test');
        $task->expects(self::once())->method('getOptions')->willReturn([
            'expression' => '* * * * *',
            'state' => 'paused',
            'last_execution' => new \DateTimeImmutable(),
        ]);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $command = new ListScheduledTaskCommand($schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:get-tasks'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringNotContainsString('[WARNING] No tasks found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('Last execution date', $tester->getDisplay());
        static::assertStringContainsString('State', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithExpressionFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('test');
        $task->expects(self::once())->method('getOptions')->willReturn([
            'expression' => '* * * * *',
            'state' => 'paused',
            'last_execution' => new \DateTimeImmutable(),
        ]);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
        $taskList->expects(self::once())->method('filter')->willReturn($taskList);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $command = new ListScheduledTaskCommand($schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:get-tasks'));
        $tester->execute([
            'scheduler' => 'foo',
            '--expression' => '* * * * *',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringNotContainsString('[WARNING] No tasks found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('Last execution date', $tester->getDisplay());
        static::assertStringContainsString('State', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithStateFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('test');
        $task->expects(self::once())->method('getOptions')->willReturn([
            'expression' => '* * * * *',
            'state' => 'paused',
            'last_execution' => new \DateTimeImmutable(),
        ]);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
        $taskList->expects(self::once())->method('filter')->willReturn($taskList);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $command = new ListScheduledTaskCommand($schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:get-tasks'));
        $tester->execute([
            'scheduler' => 'foo',
            '--state' => 'paused',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringNotContainsString('[WARNING] No tasks found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('Last execution date', $tester->getDisplay());
        static::assertStringContainsString('State', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithStateAndExpressionFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('test');
        $task->expects(self::once())->method('getOptions')->willReturn([
            'expression' => '* * * * *',
            'state' => 'paused',
            'last_execution' => new \DateTimeImmutable(),
        ]);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
        $taskList->expects(self::exactly(2))->method('filter')->willReturn($taskList);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $command = new ListScheduledTaskCommand($schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:get-tasks'));
        $tester->execute([
            'scheduler' => 'foo',
            '--state' => 'paused',
            '--expression' => '* * * * *',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringNotContainsString('[WARNING] No tasks found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('Last execution date', $tester->getDisplay());
        static::assertStringContainsString('State', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidExpressionFilter(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);
        $taskList->expects(self::once())->method('filter')->willReturn($taskList);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $command = new ListScheduledTaskCommand($schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:get-tasks'));
        $tester->execute([
            'scheduler' => 'foo',
            '--expression' => '* * * * *',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidStateFilter(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);
        $taskList->expects(self::once())->method('filter')->willReturn($taskList);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $command = new ListScheduledTaskCommand($schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:get-tasks'));
        $tester->execute([
            'scheduler' => 'foo',
            '--state' => 'started',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidStateAndExpressionFilter(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);
        $taskList->expects(self::exactly(2))->method('filter')->willReturn($taskList);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $command = new ListScheduledTaskCommand($schedulerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:get-tasks'));
        $tester->execute([
            'scheduler' => 'foo',
            '--state' => 'started',
            '--expression' => '* * * * *',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }
}

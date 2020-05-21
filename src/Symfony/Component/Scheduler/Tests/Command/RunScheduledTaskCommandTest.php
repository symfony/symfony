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
use Symfony\Component\Scheduler\Command\RunScheduledTaskCommand;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RunScheduledTaskCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerRegistryInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $command = new RunScheduledTaskCommand($scheduler, $worker);

        static::assertSame('scheduler:run', $command->getName());
        static::assertSame('Run the scheduled tasks', $command->getDescription());
        static::assertNotNull($command->getDefinition());
    }

    public function testCommandCannotExecuteEmptyTasksList(): void
    {
        $tasksList = $this->createMock(TaskListInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($tasksList);

        $worker = $this->createMock(WorkerInterface::class);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $command = new RunScheduledTaskCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:run'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function testCommandCannotExecuteEmptyTasksListWithNameFilter(): void
    {
        $tasksList = $this->createMock(TaskListInterface::class);
        $tasksList->expects(self::once())->method('filter')->willReturnSelf();

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($tasksList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);

        $command = new RunScheduledTaskCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:run'));
        $tester->execute([
            'scheduler' => 'foo',
            '--name' => 'app',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function testCommandCannotExecuteEmptyTasksListWithExpressionFilter(): void
    {
        $tasksList = $this->createMock(TaskListInterface::class);
        $tasksList->expects(self::once())->method('filter')->willReturnSelf();

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($tasksList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);

        $command = new RunScheduledTaskCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:run'));
        $tester->execute([
            'scheduler' => 'foo',
            '--expression' => '* * * * *',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function testCommandCannotExecuteEmptyTasksListWithMetadataFilter(): void
    {
        $tasksList = $this->createMock(TaskListInterface::class);
        $tasksList->expects(self::once())->method('filter')->willReturnSelf();

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($tasksList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);

        $command = new RunScheduledTaskCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:run'));
        $tester->execute([
            'scheduler' => 'foo',
            '--metadata' => 'last_execution',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }
}

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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Command\RebootSchedulerCommand;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RebootSchedulerCommandTest extends TestCase
{
    public function testRebootCannotBeUsedOnInvalidScheduler(): void
    {
        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->will(self::throwException(new InvalidArgumentException()));

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('[ERROR] The desired scheduler "foo" cannot be found!', $tester->getDisplay());
    }

    public function testRebootCanBeUsedOnValidSchedulerAndEmptyWorkerListAndDefaultRetryStrategy(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::never())->method('getIterator')->willReturn(new \ArrayIterator([]));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::exactly(5))->method('filter')->willReturn([]);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] The scheduler cannot be rebooted as the worker is not available, retrying to access it', $tester->getDisplay());
        static::assertStringContainsString('[ERROR] No worker have been found, please consider relaunching the command', $tester->getDisplay());
    }

    public function testRebootCanBeUsedOnValidSchedulerAndEmptyWorkerListAndCustomRetryStrategy(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::never())->method('getIterator')->willReturn(new \ArrayIterator([]));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::exactly(10))->method('filter')->willReturn([]);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
            '--retry' => 10,
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] The scheduler cannot be rebooted as the worker is not available, retrying to access it', $tester->getDisplay());
        static::assertStringContainsString('[ERROR] No worker have been found, please consider relaunching the command', $tester->getDisplay());
    }

    public function testRebootCanBeUsedOnValidSchedulerAndEmptyTasksListAndDefaultRetryStrategy(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::never())->method('filter')->willReturn([$worker]);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The desired scheduler "foo" have been rebooted', $tester->getDisplay());
    }

    public function testRebootCanBeUsedOnValidSchedulerAndEmptyTasksListAndCustomRetryStrategy(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::never())->method('filter')->willReturn([$worker]);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
            '--retry' => 10,
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The desired scheduler "foo" have been rebooted', $tester->getDisplay());
    }

    public function testRebootCanBeUsedOnValidSchedulerAndHydratedTasksListAndDefaultRetryStrategy(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::once())->method('filter')->willReturn([$worker]);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The desired scheduler "foo" have been rebooted', $tester->getDisplay());
    }

    public function testRebootCanBeUsedOnValidSchedulerAndHydratedTasksListAndCustomRetryStrategy(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);
        $workerRegistry->expects(self::once())->method('filter')->willReturn([$worker]);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
            '--retry' => 10,
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The desired scheduler "foo" have been rebooted', $tester->getDisplay());
    }

    public function testCommandCannotDryRunTheSchedulerRebootOnEmptyRebootTasks(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
            '--dry-run' => true,
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] The scheduler does not contain any tasks planned for the reboot process', $tester->getDisplay());
    }

    public function testCommandCanDryRunTheSchedulerReboot(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
        $taskList->expects(self::once())->method('filter')->willReturnSelf();

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $workerRegistry = $this->createMock(WorkerRegistryInterface::class);

        $command = new RebootSchedulerCommand($schedulerRegistry, $workerRegistry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
            '--dry-run' => true,
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The following tasks are planned to be executed when the scheduler will reboot:', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Type', $tester->getDisplay());
    }
}

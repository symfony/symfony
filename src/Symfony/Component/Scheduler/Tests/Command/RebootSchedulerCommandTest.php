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
use Symfony\Component\Scheduler\Command\RebootSchedulerCommand;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RebootSchedulerCommandTest extends TestCase
{
    public function testRebootCannotBeUsedOnInvalidScheduler(): void
    {
        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->will(self::throwException(new InvalidArgumentException()));

        $worker = $this->createMock(WorkerInterface::class);

        $command = new RebootSchedulerCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('[ERROR] The desired scheduler "foo" cannot be found!', $tester->getDisplay());
    }

    public function testRebootCanBeUsedOnValidSchedulerAndEmptyTasksList(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);

        $command = new RebootSchedulerCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The desired scheduler "foo" have been rebooted', $tester->getDisplay());
    }

    public function testRebootCanBeUsedOnValidSchedulerAndHydratedTasksList(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
        $taskList->expects(self::never())->method('filter');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $schedulerRegistry = $this->createMock(SchedulerRegistryInterface::class);
        $schedulerRegistry->expects(self::once())->method('get')->with('foo')->willReturn($scheduler);

        $worker = $this->createMock(WorkerInterface::class);

        $command = new RebootSchedulerCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
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

        $worker = $this->createMock(WorkerInterface::class);

        $command = new RebootSchedulerCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
            '--dry-run' => true,
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
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

        $worker = $this->createMock(WorkerInterface::class);

        $command = new RebootSchedulerCommand($schedulerRegistry, $worker);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:reboot'));
        $tester->execute([
            'scheduler' => 'foo',
            '--dry-run' => true,
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The following tasks are planned to be executed when the scheduler will reboot:', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Type', $tester->getDisplay());
    }
}

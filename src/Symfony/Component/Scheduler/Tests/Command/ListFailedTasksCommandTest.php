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
use Symfony\Component\Scheduler\Command\ListFailedTasksCommand;
use Symfony\Component\Scheduler\Task\FailedTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Scheduler\Worker\WorkerRegistryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ListFailedTasksCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $registry = $this->createMock(WorkerRegistryInterface::class);

        $command = new ListFailedTasksCommand($registry);

        static::assertSame('scheduler:list-failed', $command->getName());
        static::assertSame('List all the failed tasks', $command->getDescription());
    }

    public function testCommandCannotListWithEmptyWorkers(): void
    {
        $registry = $this->createMock(WorkerRegistryInterface::class);
        $registry->expects(self::once())->method('toArray')->willReturn([]);

        $command = new ListFailedTasksCommand($registry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:list-failed'));
        $tester->execute([]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('No worker found', $tester->getDisplay());
    }

    public function testCommandCanListWithNonEmptyWorkers(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $failedTask = new FailedTask($task, 'Foo error occurred');

        $failedTasks = $this->createMock(TaskListInterface::class);
        $failedTasks->expects(self::once())->method('toArray')->willReturn([$failedTask]);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($failedTasks);

        $registry = $this->createMock(WorkerRegistryInterface::class);
        $registry->expects(self::once())->method('toArray')->willReturn(['foo' => $worker]);

        $command = new ListFailedTasksCommand($registry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:list-failed'));
        $tester->execute([]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('Worker', $tester->getDisplay());
        static::assertStringContainsString('Task', $tester->getDisplay());
        static::assertStringContainsString('Reason', $tester->getDisplay());
        static::assertStringContainsString('Date', $tester->getDisplay());
    }

    public function testCommandCanListForSpecificWorker(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $failedTask = new FailedTask($task, 'Foo error occurred');

        $failedTasks = $this->createMock(TaskListInterface::class);
        $failedTasks->expects(self::once())->method('toArray')->willReturn([$failedTask]);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($failedTasks);

        $registry = $this->createMock(WorkerRegistryInterface::class);
        $registry->expects(self::never())->method('toArray')->willReturn(['foo' => $worker]);
        $registry->expects(self::once())->method('filter')->willReturn([$worker]);

        $command = new ListFailedTasksCommand($registry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:list-failed'));
        $tester->execute([
            'worker' => 'foo',
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('Worker', $tester->getDisplay());
        static::assertStringContainsString('Task', $tester->getDisplay());
        static::assertStringContainsString('Reason', $tester->getDisplay());
        static::assertStringContainsString('Date', $tester->getDisplay());
    }
}

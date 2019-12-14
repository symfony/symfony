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
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Scheduler\Exception\UndefinedRunnerException;
use Symfony\Component\Scheduler\Runner\RunnerInterface;
use Symfony\Component\Scheduler\Runner\ShellTaskRunner;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcherInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\Worker;
use Symfony\Component\Scheduler\Worker\WorkerLockInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerTest extends TestCase
{
    public function testTaskCantBeExecutedWithoutRunner(): void
    {
        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);
        $runner = $this->createMock(RunnerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $workerLock = $this->createMock(WorkerLockInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('foo');

        $worker = new Worker([$runner], $tracker, $workerLock, $logger);

        static::expectException(UndefinedRunnerException::class);
        static::expectExceptionMessage('No runner found for the given task "foo"');
        $worker->execute($task);
    }

    public function testTaskCanBeExecutedWithRunner(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('acquire')->willReturn(true);

        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('foo');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->method('support')->willReturn(true);
        $runner->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $workerLock = $this->createMock(WorkerLockInterface::class);
        $workerLock->expects(self::once())->method('getLock')->willReturn($lock);

        $worker = new Worker([$runner], $tracker, $workerLock, $logger);

        $worker->execute($task);
    }

    public function testTaskCannotBeExecutedTwiceWithLock(): void
    {
        $task = new ShellTask('foo', 'echo Symfony', ['expression' => '* * * * *', 'isolated' => true]);

        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('acquire')->willReturn(true);

        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);

        $workerLock = $this->createMock(WorkerLockInterface::class);
        $workerLock->expects(self::once())->method('getLock')->willReturn($lock);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::never())->method('support')->willReturn(true);
        $secondRunner->expects(self::never())->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker([$runner, $secondRunner], $tracker, $workerLock, $logger);

        $worker->execute($task);
    }

    /**
     * @param array $context
     *
     * @dataProvider provideShellTasks
     */
    public function testTaskCanBeExecutedAndOutputCanBeRetrieved(array $context): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('acquire')->willReturn(true);

        $workerLock = $this->createMock(WorkerLockInterface::class);
        $workerLock->expects(self::once())->method('getLock')->willReturn($lock);

        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker([$context['runner']], $tracker, $workerLock, $logger);

        $worker->execute($context['task']);
    }

    public function provideShellTasks(): \Generator
    {
        yield 'echo tasks' => [
            [
                'task' => new ShellTask('foo', 'echo Symfony', ['arguments' => ['env' => 'test'], 'output' => true]),
                'runner' => new ShellTaskRunner(),
            ],
            [
                'task' => new ShellTask('bar', 'echo Symfony', ['output' => true]),
                'runner' => new ShellTaskRunner(),
            ],
        ];
    }
}

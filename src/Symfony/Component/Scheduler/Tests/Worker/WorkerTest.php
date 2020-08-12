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
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\UndefinedRunnerException;
use Symfony\Component\Scheduler\Runner\RunnerInterface;
use Symfony\Component\Scheduler\Runner\ShellTaskRunner;
use Symfony\Component\Scheduler\Task\ChainedTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcherInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\Worker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerTest extends TestCase
{
    public function testTaskCantBeExecutedWithoutRunner(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('foo');

        $worker = new Worker([], $watcher, $eventDispatcher, $logger);

        static::expectException(UndefinedRunnerException::class);
        static::expectExceptionMessage('No runner found supporting the given task "foo"');
        $worker->execute($task);
    }

    public function testTaskCantBeExecutedWithoutCapableRunner(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(false);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('foo');

        $worker = new Worker([$runner], $watcher, $eventDispatcher, $logger);

        static::expectException(UndefinedRunnerException::class);
        static::expectExceptionMessage('No runner found supporting the given task "foo"');
        $worker->execute($task);
    }

    public function testTaskCanBeExecutedWithRunner(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(4))->method('dispatch');

        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('foo');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->method('support')->willReturn(true);
        $runner->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $worker = new Worker([$runner], $tracker, $eventDispatcher, $logger);
        $worker->execute($task);
    }

    public function testWorkerCannotHandleInvalidChainedTasks(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $store = $this->createMock(BlockingStoreInterface::class);
        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);

        $task = new ShellTask('foo', 'echo Symfony', [
            'depends_on' => ['bar'],
            'expression' => '* * * * *',
            'isolated' => true
        ]);
        $secondTask = new ShellTask('bar', 'echo Symfony', [
            'depends_on' => ['foo'],
            'expression' => '* * * * *',
            'isolated' => true
        ]);
        $chainedTask = new ChainedTask('foo_chained', [$task, $secondTask]);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::exactly(1))->method('support')->willReturn(true);
        $runner->expects(self::never())->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::never())->method('support')->willReturn(true);
        $secondRunner->expects(self::never())->method('run')->willReturn(Output::forSuccess($chainedTask, 0, null));

        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker([$runner, $secondRunner], $tracker, $eventDispatcher, $logger, $store);

        static::expectException(InvalidArgumentException::class);
        $worker->execute($chainedTask);

        static::assertNotEmpty($worker->getFailedTasks());
        static::assertArrayHasKey('foo_chained', $worker->getFailedTasks()->toArray());
    }

    public function testWorkerCanHandleChainedTasks(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $store = $this->createMock(BlockingStoreInterface::class);
        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);

        $task = new ShellTask('foo', 'echo Symfony', [
            'depends_on' => ['bar'],
            'expression' => '* * * * *',
            'isolated' => true
        ]);
        $secondTask = new ShellTask('bar', 'echo Symfony', [
            'expression' => '* * * * *',
            'isolated' => true
        ]);
        $chainedTask = new ChainedTask('foo', [$task, $secondTask]);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::exactly(3))->method('support')->willReturn(true);
        $runner->expects(self::exactly(3))->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::never())->method('support')->willReturn(true);
        $secondRunner->expects(self::never())->method('run')->willReturn(Output::forSuccess($chainedTask, 0, null));

        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker([$runner, $secondRunner], $tracker, $eventDispatcher, $logger, $store);
        $worker->execute($chainedTask);
    }

    public function testWorkerCanHandleSingleRunTask(): void
    {
    }

    public function testTaskCannotBeExecutedTwiceWithLock(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $store = $this->createMock(BlockingStoreInterface::class);
        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);

        $task = new ShellTask('foo', 'echo Symfony', ['expression' => '* * * * *', 'isolated' => true]);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::never())->method('support')->willReturn(true);
        $secondRunner->expects(self::never())->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker([$runner, $secondRunner], $tracker, $eventDispatcher, $logger, $store);
        $worker->execute($task);
    }

    public function testWorkerCanHandleTaskAndWatchExecutionTime(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $store = $this->createMock(BlockingStoreInterface::class);

        $watcher = $this->createMock(TaskExecutionWatcherInterface::class);
        $watcher->expects(self::once())->method('watch');
        $watcher->expects(self::once())->method('endWatch');

        $task = new ShellTask('foo', 'echo Symfony', ['expression' => '* * * * *', 'isolated' => true]);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::never())->method('support')->willReturn(true);
        $secondRunner->expects(self::never())->method('run')->willReturn(Output::forSuccess($task, 0, null));

        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker([$runner, $secondRunner], $watcher, $eventDispatcher, $logger, $store);
        $worker->execute($task);
    }

    public function testWorkerCanHandleFailedTask(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(4))->method('dispatch');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->method('support')->willReturn(true);
        $runner->method('run')->willThrowException(new \RuntimeException());

        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('foo');

        $worker = new Worker([$runner], $tracker, $eventDispatcher, $logger);

        $worker->execute($task);
        static::assertNotEmpty($worker->getFailedTasks());
    }

    /**
     * @param array $context
     *
     * @dataProvider provideShellTasks
     */
    public function testTaskCanBeExecutedAndOutputCanBeRetrieved(array $context): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(4))->method('dispatch');

        $tracker = $this->createMock(TaskExecutionWatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker([$context['runner']], $tracker, $eventDispatcher, $logger);
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

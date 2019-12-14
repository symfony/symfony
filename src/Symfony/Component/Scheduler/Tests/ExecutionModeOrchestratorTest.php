<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionModeOrchestratorTest extends TestCase
{
    public function testListCannotBeSortedWithInvalidMode(): void
    {
        $orchestrator = new ExecutionModeOrchestrator('test');

        static::expectException(\LogicException::class);
        $orchestrator->sort([]);
    }

    public function testListCanBeUsingDefaultMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);

        $orchestrator = new ExecutionModeOrchestrator();
        $tasks = $orchestrator->sort([$secondTask, $task]);

        static::assertSame([
            0 => $secondTask,
            1 => $task
        ], $tasks);
    }

    public function testListCanBeUsingRoundFifoMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);
        $thirdTask = $this->createMock(TaskInterface::class);
        $task->method('get')->willReturn(200);
        $secondTask->method('get')->willReturn(100);
        $thirdTask->method('get')->willReturn(75);

        $orchestrator = new ExecutionModeOrchestrator();
        $tasks = $orchestrator->sort([$secondTask, $task, $thirdTask]);

        static::assertSame([
            1 => $task,
            0 => $secondTask,
            2 => $thirdTask
        ], $tasks);
    }

    public function testListCanBeUsingRoundRobinTimedMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);
        $task->method('get')->willReturnOnConsecutiveCalls(2.10, 2.50);
        $secondTask->method('get')->willReturnOnConsecutiveCalls(2.35, 2.50);

        $orchestrator = new ExecutionModeOrchestrator(ExecutionModeOrchestrator::ROUND_ROBIN);
        $tasks = $orchestrator->sort([$secondTask, $task]);

        static::assertSame([
            1 => $task,
            0 => $secondTask
        ], $tasks);
    }

    public function testListCanBeUsingDeadlineMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);
        $task->method('get')->willReturnOnConsecutiveCalls(null, new \DateTimeImmutable('+ 2 hour'), 2.10, 2.50);
        $secondTask->method('get')->willReturnOnConsecutiveCalls(null, new \DateTimeImmutable('+ 1 hour'), 2.35, 2.50);

        $orchestrator = new ExecutionModeOrchestrator(ExecutionModeOrchestrator::DEADLINE);
        $tasks = $orchestrator->sort([$secondTask, $task]);

        static::assertSame([
            1 => $task,
            0 => $secondTask
        ], $tasks);
    }

    public function testListCanBeUsingBatchMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);
        $task->method('get')->willReturnOnConsecutiveCalls(2.10, 2.50);
        $secondTask->method('get')->willReturnOnConsecutiveCalls(2.35, 2.50);

        $orchestrator = new ExecutionModeOrchestrator(ExecutionModeOrchestrator::BATCH);
        $tasks = $orchestrator->sort([$secondTask, $task]);

        static::assertCount(2, $tasks);
    }

    public function testListCanBeUsingIdleMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);
        $task->method('get')->willReturnOnConsecutiveCalls(-20);
        $secondTask->method('get')->willReturnOnConsecutiveCalls(-10);

        $orchestrator = new ExecutionModeOrchestrator(ExecutionModeOrchestrator::IDLE);
        $tasks = $orchestrator->sort([$secondTask, $task]);

        static::assertCount(2, $tasks);
        static::assertSame([
            1 => $task,
            0 => $secondTask
        ], $tasks);
    }
}

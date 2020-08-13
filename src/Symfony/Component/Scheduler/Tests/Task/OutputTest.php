<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Task;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class OutputTest extends TestCase
{
    public function testOutputCanBeCreatedForSuccess(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $output = Output::forSuccess($task, 1);

        static::assertSame(1, $output->getExitCode());
        static::assertSame('undefined', $output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame('success', $output->getType());
    }

    public function testOutputCanBeCreatedForError(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $output = Output::forError($task, 1);

        static::assertSame(1, $output->getExitCode());
        static::assertSame('undefined', $output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame('error', $output->getType());
    }

    public function testOutputCanBeCreatedForScriptTerminated(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $output = Output::forScriptTerminated($task, 130);

        static::assertSame(130, $output->getExitCode());
        static::assertSame('undefined', $output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame('terminated', $output->getType());
    }

    public function testOutputCanBeCreatedForCli(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $output = Output::forCli($task, 130, 'foo');

        static::assertSame(130, $output->getExitCode());
        static::assertSame('foo', $output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame('terminated', $output->getType());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Runner\ShellTaskRunner;
use Symfony\Component\Scheduler\Task\AbstractTask;
use Symfony\Component\Scheduler\Task\ShellTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskRunnerTest extends TestCase
{
    public function testRunnerCantSupportWrongTask(): void
    {
        $task = new FooTask('test');

        $runner = new ShellTaskRunner();
        static::assertFalse($runner->support($task));
    }

    public function testRunnerCanSupportValidTaskWithoutOutput(): void
    {
        $task = new ShellTask('test', 'echo Symfony', [
            'arguments' => ['env' => 'test'],
            'timeout' => 10,
        ]);

        $runner = new ShellTaskRunner();
        static::assertTrue($runner->support($task));
        static::assertNull($runner->run($task)->getOutput());
    }

    public function testRunnerCanSupportValidTaskWithOutput(): void
    {
        $task = new ShellTask('test', 'echo Symfony', [
            'arguments' => ['env' => 'test'],
            'output' => true,
        ]);

        $runner = new ShellTaskRunner();
        static::assertTrue($runner->support($task));
        static::assertSame('Symfony', $runner->run($task)->getOutput());
    }

    public function testRunnerCanSupportValidTaskWithSpecificEnvVariablesButWithoutOutput(): void
    {
        $task = new ShellTask('test', 'echo "${:MESSAGE}"', [
            'env' => ['MESSAGE' => 'bar'],
        ]);

        $runner = new ShellTaskRunner();
        static::assertTrue($runner->support($task));
        static::assertNull($runner->run($task)->getOutput());
    }

    public function testRunnerCanSupportValidTaskWithSpecificEnvVariablesAndWithOutput(): void
    {
        $task = new ShellTask('test', 'echo "${:MESSAGE}" from "${:PROCESS}"', [
            'arguments' => ['PROCESS' => 'Scheduler'],
            'env' => ['MESSAGE' => 'bar'],
            'output' => true,
        ]);

        $runner = new ShellTaskRunner();
        static::assertTrue($runner->support($task));
        static::assertSame('bar from Scheduler', $runner->run($task)->getOutput());
    }

    public function testRunnerCanSupportIsolatedTask(): void
    {
        $task = new ShellTask('test', 'echo Symfony', [
            'output' => true,
            'isolated' => true,
        ]);

        $runner = new ShellTaskRunner();
        static::assertTrue($runner->support($task));
        static::assertSame('Symfony', $runner->run($task)->getOutput());
    }
}

final class FooTask extends AbstractTask
{
}

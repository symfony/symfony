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
use Symfony\Component\Scheduler\Runner\CallBackTaskRunner;
use Symfony\Component\Scheduler\Task\CallBackTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\ShellTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallBackTaskRunnerTest extends TestCase
{
    public function testRunnerCannotSupportInvalidTask(): void
    {
        $runner = new CallBackTaskRunner();

        $task = new ShellTask('foo', 'echo Symfony!');
        static::assertFalse($runner->support($task));

        $task = new CallBackTask('foo', function () {
            return 1 + 1;
        });
        static::assertTrue($runner->support($task));
    }

    public function testRunnerCanExecuteValidTask(): void
    {
        $runner = new CallBackTaskRunner();
        $task = new CallBackTask('foo', function () {
            return 1 + 1;
        });

        static::assertInstanceOf(Output::class, $runner->run($task));
        static::assertSame('2', $runner->run($task)->getOutput());
    }

    public function testRunnerCanExecuteValidTaskWithArguments(): void
    {
        $runner = new CallBackTaskRunner();
        $task = new CallBackTask('foo', function ($a, $b) {
            return $a * $b;
        }, [1, 2]);

        static::assertInstanceOf(Output::class, $runner->run($task));
        static::assertSame('2', $runner->run($task)->getOutput());
    }
}

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
use Symfony\Component\Scheduler\Runner\NullTaskRunner;
use Symfony\Component\Scheduler\Task\AbstractTask;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\Output;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTaskRunnerTest extends TestCase
{
    public function testRunnerCannotSupportWrongTask(): void
    {
        $task = new BarTask('test');

        $runner = new NullTaskRunner();
        static::assertFalse($runner->support($task));
    }

    public function testOutputIsReturned(): void
    {
        $task = new NullTask('test');

        $runner = new NullTaskRunner();
        $output = $runner->run($task);

        static::assertInstanceOf(Output::class, $output);
        static::assertSame(0, $output->getExitCode());
        static::assertNull($output->getOutput());
    }
}

final class BarTask extends AbstractTask
{
}

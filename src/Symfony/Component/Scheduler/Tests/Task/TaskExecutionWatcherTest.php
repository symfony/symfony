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
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcher;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionWatcherTest extends TestCase
{
    /**
     * @dataProvider provideUnTrackedTasks
     */
    public function testTrackerCannotTrackInvalidTask(TaskInterface $task): void
    {
        $tracker = new TaskExecutionWatcher(new Stopwatch());

        $tracker->watch($task);
        sleep(1);
        $tracker->endWatch($task);

        static::assertNull($task->get('execution_computation_time'));
    }

    /**
     * @dataProvider provideTrackedTasks
     */
    public function testTrackerCanTrack(TaskInterface $task): void
    {
        $tracker = new TaskExecutionWatcher(new Stopwatch());

        $tracker->watch($task);
        sleep(1);
        $tracker->endWatch($task);

        static::assertNotNull($task->get('execution_computation_time'));
    }

    public function provideTrackedTasks(): \Generator
    {
        yield [
            new ShellTask('Http AbstractTask - Hello', 'echo Symfony', ['expression' => '* * * * *', 'tracked' => true]),
            new ShellTask('Http AbstractTask - Test', 'echo Symfony', ['expression' => '* * * * *', 'tracked' => true]),
        ];
    }

    public function provideUnTrackedTasks(): \Generator
    {
        yield [
            new ShellTask('Http AbstractTask - Hello', 'echo Symfony', ['expression' => '* * * * *']),
            new ShellTask('Http AbstractTask - Test', 'echo Symfony', ['expression' => '* * * * *']),
        ];
    }
}

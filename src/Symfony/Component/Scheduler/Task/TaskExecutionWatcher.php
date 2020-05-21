<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionWatcher implements TaskExecutionWatcherInterface
{
    private $watch;

    public function __construct(Stopwatch $watch)
    {
        $this->watch = $watch;
    }

    public function watch(TaskInterface $task): void
    {
        if (!$task->get('tracked')) {
            return;
        }

        $this->watch->start(sprintf('task_execution.%s', $task->getName()));
    }

    public function endWatch(TaskInterface $task): void
    {
        if (!$task->get('tracked')) {
            return;
        }

        if (!$this->watch->isStarted(sprintf('task_execution.%s', $task->getName()))) {
            return;
        }

        $event = $this->watch->start(sprintf('task_execution.%s', $task->getName()));
        $task->set('execution_computation_time', $event->getDuration());
    }
}

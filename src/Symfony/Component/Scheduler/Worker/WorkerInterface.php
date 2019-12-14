<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Worker;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerInterface
{
    /**
     * Execute the given task, if the task cannot be executed, the worker should exit.
     *
     * An exception can be throw during the execution of the task, if so, it SHOULD be handled.
     *
     * A worker SHOULD dispatch the following events:
     *  - WorkerStartedEvent: Contain the worker instance BEFORE executing the task.
     *  - TaskToExecuteEvent: Contain the task to executed BEFORE executing the task.
     *  - TaskExecutedEvent: Contain the task to executed AFTER executing the task and its output (if defined).
     *  - WorkerStoppedEvent: Contain the worker instance AFTER executing the task.
     */
    public function execute(TaskInterface $task): void;

    public function stop(): void;

    public function isRunning(): bool;

    public function getFailedTasks(): TaskListInterface;

    public function addSubscriber(EventSubscriberInterface $subscriber): void;
}

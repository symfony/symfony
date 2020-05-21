<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use DateTimeZone;
use Symfony\Component\Scheduler\Bag\BagInterface;
use Symfony\Component\Scheduler\EventListener\SchedulerSubscriberInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulerInterface
{
    /**
     * Schedule a specific task, the storage of the task is up to the scheduler.
     *
     * @param TaskInterface        $task
     * @param array|BagInterface[] $bags
     */
    public function schedule(TaskInterface $task, array $bags = []): void;

    /**
     * Un-schedule a specific task, once un-scheduled, the task is removed from the scheduler.
     *
     * @param string $taskName
     */
    public function unSchedule(string $taskName): void;

    /**
     * Update a specific task, the name should NOT be changed, every metadata can.
     *
     * @param string        $taskName
     * @param TaskInterface $task
     */
    public function update(string $taskName, TaskInterface $task): void;

    /**
     * Pause a specific task, when paused, a task cannot be executed by the worker (but it can be sent to it).
     *
     * @param string $taskName
     */
    public function pause(string $taskName): void;

    /**
     * Re-enable a specific task (if disabled or paused), once resumed, the task can be executed.
     *
     * @param string $taskName
     */
    public function resume(string $taskName): void;

    /**
     * Allow to retrieve every due tasks, the logic used to build the TaskList is own to the scheduler
     *
     * @return TaskListInterface
     */
    public function getDueTasks(): TaskListInterface;

    /**
     * Return the timezone used by the actual scheduler, each scheduler can use a different timezone.
     *
     * @return DateTimeZone
     */
    public function getTimezone(): DateTimeZone;

    /**
     * Return every tasks scheduled.
     *
     * @return TaskListInterface
     */
    public function getTasks(): TaskListInterface;

    /**
     * Remove every tasks except the ones that use the 'reboot' expression.
     *
     * The "reboot" tasks are re-scheduled and MUST be executed as soon as possible.
     */
    public function reboot(): void;

    /**
     * Attach a subscriber to the internal scheduler EventDispatcher instance.
     *
     * The subscriber SHOULD respect the contract of {@see SchedulerSubscriberInterface::getSubscribedSchedulers()}
     */
    public function addSubscriber(SchedulerSubscriberInterface $subscriber): void;
}

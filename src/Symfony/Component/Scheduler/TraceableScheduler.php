<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TraceableScheduler implements SchedulerInterface
{
    private $scheduler;
    private $scheduledTasks = [];

    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $this->scheduledTasks[$task->getName()] = $task->getFormattedInformations();

        $this->scheduler->schedule($task);
    }

    /**
     * {@inheritdoc}
     */
    public function unSchedule(string $taskName): void
    {
        $this->scheduler->unSchedule($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task): void
    {
        $this->scheduler->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->scheduler->pause($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->scheduler->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(): TaskListInterface
    {
        return $this->scheduler->getDueTasks();
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): \DateTimeZone
    {
        return $this->scheduler->getTimezone();
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(): TaskListInterface
    {
        return $this->scheduler->getTasks();
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $this->scheduler->reboot();
    }

    public function getScheduledTasks(): array
    {
        return $this->scheduledTasks;
    }
}

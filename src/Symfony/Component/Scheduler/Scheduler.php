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

use Cron\CronExpression;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Event\SchedulerRebootedEvent;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\Messenger\TaskMessage;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Scheduler implements SchedulerInterface
{
    private $timezone;
    private $transport;
    private $eventDispatcher;
    private $bus;

    public function __construct(\DateTimeZone $timezone, TransportInterface $transport, EventDispatcherInterface $eventDispatcher = null, MessageBusInterface $bus = null)
    {
        $this->timezone = $timezone;
        $this->transport = $transport;
        $this->eventDispatcher = $eventDispatcher;
        $this->bus = $bus;
    }

    public static function forSpecificTimezone(\DateTimeZone $timezone, TransportInterface $transport, EventDispatcherInterface $eventDispatcher = null, MessageBusInterface $bus = null): SchedulerInterface
    {
        return new self($timezone, $transport, $eventDispatcher, $bus);
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $task->setMultiples([
            'arrival_time' => new \DateTimeImmutable(),
            'timezone' => $this->timezone,
        ]);

        if (null !== $this->bus && $task->get('queued')) {
            $this->bus->dispatch(new TaskMessage($task));
            $this->dispatchEvent(new TaskScheduledEvent($task));

            return;
        }

        $this->transport->create($task);
        $this->dispatchEvent(new TaskScheduledEvent($task));
    }

    /**
     * {@inheritdoc}
     */
    public function unSchedule(string $taskName): void
    {
        $this->transport->delete($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task): void
    {
        $this->transport->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->transport->pause($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->transport->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(): TaskListInterface
    {
        return $this->transport->list()->filter(function (TaskInterface $task): bool {
            return CronExpression::factory($task->get('expression'))->isDue(new \DateTimeImmutable(), $task->get('timezone')->getName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): \DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(): TaskListInterface
    {
        return $this->transport->list();
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $rebootTasks = $this->getTasks()->filter(function (TaskInterface $task): bool {
            return '@reboot' === $task->get('expression');
        });

        $this->transport->empty();

        foreach ($rebootTasks as $task) {
            $this->transport->create($task);
        }

        $this->dispatchEvent(new SchedulerRebootedEvent($this));
    }

    private function dispatchEvent(Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}

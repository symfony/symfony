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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Event\SchedulerRebootedEvent;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\Event\TaskUnscheduledEvent;
use Symfony\Component\Scheduler\EventListener\SchedulerSubscriberInterface;
use Symfony\Component\Scheduler\Messenger\TaskMessage;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Scheduler implements SchedulerInterface
{
    private $timezone;
    private $transport;
    private $eventDispatcher;
    private $bus;
    private $bagRegistry;

    public function __construct(\DateTimeZone $timezone, TransportInterface $transport, BagRegistryInterface $bagRegistry = null, MessageBusInterface $bus = null)
    {
        $this->timezone = $timezone;
        $this->transport = $transport;
        $this->bagRegistry = $bagRegistry;
        $this->eventDispatcher = new EventDispatcher();
        $this->bus = $bus;
    }

    public static function forSpecificTimezone(\DateTimeZone $timezone, TransportInterface $transport, BagRegistryInterface $bagRegistry = null, MessageBusInterface $bus = null): SchedulerInterface
    {
        return new self($timezone, $transport, $bagRegistry, $bus);
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task, array $bags = []): void
    {
        $task->setMultiples([
            'arrival_time' => new \DateTimeImmutable(),
            'timezone' => $this->timezone,
        ]);

        if (null !== $this->bus && $task->get('queued')) {
            $this->bus->dispatch(new TaskMessage($task));
            $this->handleBags($task, $bags);

            $this->eventDispatcher->dispatch(new TaskScheduledEvent($task));

            return;
        }

        $this->transport->create($task);
        $this->handleBags($task, $bags);

        $this->eventDispatcher->dispatch(new TaskScheduledEvent($task));
    }

    /**
     * {@inheritdoc}
     */
    public function unSchedule(string $taskName): void
    {
        $this->transport->delete($taskName);

        $this->eventDispatcher->dispatch(new TaskUnscheduledEvent($taskName));
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

        $this->transport->clear();

        foreach ($rebootTasks as $task) {
            $this->transport->create($task);
        }

        $this->eventDispatcher->dispatch(new SchedulerRebootedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function addSubscriber(SchedulerSubscriberInterface $subscriber): void
    {
        $this->eventDispatcher->addSubscriber($subscriber);
    }

    private function handleBags(TaskInterface $task, array $bags = []): void
    {
        if (0 === \count($bags)) {
            return;
        }

        if (null === $this->bagRegistry) {
            return;
        }

        foreach ($bags as $bag) {
            $this->bagRegistry->register($task, $bag);
        }
    }
}

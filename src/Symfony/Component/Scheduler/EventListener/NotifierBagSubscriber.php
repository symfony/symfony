<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Bag\NotifierBag;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskToExecuteEvent;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotifierBagSubscriber implements EventSubscriberInterface
{
    private $bagRegistry;
    private $notifier;

    public function __construct(BagRegistryInterface $bagRegistry, NotifierInterface $notifier = null)
    {
        $this->bagRegistry = $bagRegistry;
        $this->notifier = $notifier;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            TaskToExecuteEvent::class => 'onTaskToExecute',
            TaskExecutedEvent::class => 'onTaskExecuted',
            TaskFailedEvent::class => 'onTaskFailed',
        ];
    }

    public function onTaskToExecute(TaskToExecuteEvent $event): void
    {
        $task = $event->getTask();

        $this->notify($task, 'before');
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $this->notify($task, 'after');
    }

    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $task = $event->getTask();

        $this->notify($task, 'failure');
    }

    private function notify(TaskInterface $task, string $bagKey): void
    {
        if (null === $this->notifier) {
            return;
        }

        $bag = $this->bagRegistry->get($task->getBag('notifier_bag'));

        if (!$bag instanceof NotifierBag) {
            return;
        }

        $notifications = $bag->getContent();

        if (0 === \count($notifications[$bagKey])) {
            return;
        }

        foreach ($notifications[$bagKey] as $notification) {
            $this->notifier->send($notification);
        }
    }
}

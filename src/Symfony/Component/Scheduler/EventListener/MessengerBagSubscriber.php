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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Bag\MessengerBag;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutingEvent;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerBagSubscriber implements EventSubscriberInterface
{
    private $bagRegistry;
    private $messageBus;

    public function __construct(BagRegistryInterface $bagRegistry, MessageBusInterface $messageBus = null)
    {
        $this->bagRegistry = $bagRegistry;
        $this->messageBus = $messageBus;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            TaskExecutingEvent::class => 'onTaskToExecute',
            TaskExecutedEvent::class => 'onTaskExecuted',
            TaskFailedEvent::class => 'onTaskFailed',
        ];
    }

    public function onTaskToExecute(TaskExecutingEvent $event): void
    {
        $task = $event->getTask();

        $this->dispatch($task, 'before');
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $this->dispatch($task, 'after');
    }

    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $task = $event->getTask();

        $this->dispatch($task, 'failure');
    }

    private function dispatch(TaskInterface $task, string $bagKey): void
    {
        if (null === $this->messageBus) {
            return;
        }

        $bag = $this->bagRegistry->get($task->getBag('messenger_bag'));

        if (!$bag instanceof MessengerBag) {
            return;
        }

        $messages = $bag->getContent();

        if (0 === \count($messages[$bagKey])) {
            return;
        }

        foreach ($messages[$bagKey] as $message) {
            $this->messageBus->dispatch($message);
        }
    }
}

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
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Bag\MercureBag;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutingEvent;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MercureBagSubscriber implements EventSubscriberInterface
{
    private $bagRegistry;
    private $publisher;

    public function __construct(BagRegistryInterface $bagRegistry, PublisherInterface $publisher = null)
    {
        $this->bagRegistry = $bagRegistry;
        $this->publisher = $publisher;
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

        $this->publish($task, 'before');
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $this->publish($task, 'after');
    }

    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $task = $event->getTask();

        $this->publish($task, 'failure');
    }

    private function publish(TaskInterface $task, string $bagKey): void
    {
        if (null === $this->publisher) {
            return;
        }

        $bag = $this->bagRegistry->get($task->getBag('mercure_bag'));

        if (!$bag instanceof MercureBag) {
            return;
        }

        $updates = $bag->getContent();

        if (0 === \count($updates[$bagKey])) {
            return;
        }

        foreach ($updates[$bagKey] as $update) {
            ($this->publisher)($update);
        }
    }
}

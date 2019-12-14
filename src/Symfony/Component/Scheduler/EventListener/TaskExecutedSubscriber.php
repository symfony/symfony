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
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutedSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            TaskExecutedEvent::class => ['onTaskExecuted', 1000],
        ];
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $task->set('last_execution', new \DateTimeImmutable());
    }
}

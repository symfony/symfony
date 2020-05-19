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

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskToExecuteEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotifierSubscriber implements WorkerSubscriberInterface
{
    private $notifier;

    public function __construct(NotifierInterface $notifier = null)
    {
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedWorkers(): array
    {
        return ['*'];
    }

    public function onTaskToExecute(TaskToExecuteEvent $event): void
    {
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
    }
}

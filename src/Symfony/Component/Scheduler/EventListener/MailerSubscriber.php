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

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskToExecuteEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MailerSubscriber implements WorkerSubscriberInterface
{
    private $mailer;

    public function __construct(MailerInterface $mailer = null)
    {
        $this->mailer = $mailer;
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

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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Bag\MailerBag;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutingEvent;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MailerBagSubscriber implements EventSubscriberInterface
{
    private $bagRegistry;
    private $mailer;

    public function __construct(BagRegistryInterface $bagRegistry, MailerInterface $mailer = null)
    {
        $this->bagRegistry = $bagRegistry;
        $this->mailer = $mailer;
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

        $this->sendMail($task, 'before');
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $this->sendMail($task, 'after');
    }

    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $task = $event->getTask();

        $this->sendMail($task, 'failure');
    }

    private function sendMail(TaskInterface $task, string $bagKey): void
    {
        $bag = $this->bagRegistry->get($task->getBag('mailer_bag'));

        if (!$bag instanceof MailerBag) {
            return;
        }

        $mails = $bag->getContent();

        if (0 === \count($mails[$bagKey])) {
            return;
        }

        if (null === $this->mailer) {
            return;
        }

        foreach ($mails[$bagKey] as $mail) {
            $this->mailer->send($mail);
        }
    }
}

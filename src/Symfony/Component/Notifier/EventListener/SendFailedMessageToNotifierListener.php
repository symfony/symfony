<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier;

/**
 * Sends a rejected message to the notifier.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SendFailedMessageToNotifierListener implements EventSubscriberInterface
{
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $throwable = $event->getThrowable();
        if ($throwable instanceof HandlerFailedException) {
            $exceptions = $throwable->getWrappedExceptions();
            $throwable = $exceptions[array_key_first($exceptions)];
        }
        $envelope = $event->getEnvelope();
        $notification = Notification::fromThrowable($throwable)->importance(Notification::IMPORTANCE_HIGH);
        $notification->subject(\sprintf('A "%s" message has just failed: %s.', $envelope->getMessage()::class, $notification->getSubject()));

        $this->notifier->send($notification, ...$this->notifier->getAdminRecipients());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }
}

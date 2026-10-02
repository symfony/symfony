<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Failure\FailureMessageDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Dispatches the failure message of a message that a worker gives up on, in place of sending the message to a failure transport.
 *
 * It also dispatches, first, the failure messages of the delayed messages that failed in their handlers while the message was handled, since they failed with it.
 * It runs after the retry listener decided whether the message is retried, and before the failure transport listener, which it tells to skip the message once its failure message is dispatched.
 * When the failure message cannot be dispatched, the message is sent to the failure transport as if it had none.
 */
final class DispatchOnFailureListener implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        if (FailureMessageDispatcher::dispatch($this->bus, $this->logger, $event->getEnvelope(), $event->getThrowable())) {
            $event->addStamps(new SentToFailureTransportStamp($event->getReceiverName()));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['method' => 'onMessageFailed', 'priority' => 0, 'after' => SendFailedMessageForRetryListener::class, 'before' => SendFailedMessageToFailureTransportListener::class],
        ];
    }
}

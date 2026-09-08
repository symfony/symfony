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
use Symfony\Component\Messenger\DispatchOnFailureTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;

/**
 * Dispatches the failure message of a message that a worker gives up on.
 *
 * It runs after the retry listener decided whether the message is retried,
 * and before the message is sent to a failure transport.
 */
final class DispatchOnFailureListener implements EventSubscriberInterface
{
    use DispatchOnFailureTrait;

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

        $envelope = $event->getEnvelope();

        if (null === $stamp = $envelope->last(DispatchOnFailureStamp::class)) {
            return;
        }

        $this->logger?->info('Dispatching {failure_class} because message {class} failed.', [
            'class' => $envelope->getMessage()::class,
            'failure_class' => Envelope::wrap($stamp->getMessage())->getMessage()::class,
        ]);

        $this->dispatchFailureMessage($envelope, $event->getThrowable());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', 0],
        ];
    }
}

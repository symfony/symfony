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

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Sends a rejected message to a "failure transport".
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class SendFailedMessageToFailureTransportListener implements EventSubscriberInterface
{
    public function __construct(
        private ContainerInterface $failureSenders,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        if (!$this->failureSenders->has($event->getReceiverName())) {
            return;
        }

        $failureSender = $this->failureSenders->get($event->getReceiverName());

        $envelope = $event->getEnvelope();

        // avoid re-sending to the failed sender
        if (null !== $envelope->last(SentToFailureTransportStamp::class)) {
            return;
        }

        $envelope = $envelope->with(
            new SentToFailureTransportStamp($event->getReceiverName()),
            new DelayStamp(0),
            new RedeliveryStamp(0)
        );

        $this->logger?->info('Rejected message {class} will be sent to the failure transport {transport}.', [
            'class' => $envelope->getMessage()::class,
            'transport' => $failureSender::class,
        ]);

        $failureSender->send($envelope);
    }

    public function onMessageSkip(WorkerMessageSkipEvent $event): void
    {
        if (!$this->failureSenders->has($event->getReceiverName())) {
            return;
        }

        $failureSender = $this->failureSenders->get($event->getReceiverName());
        $envelope = $event->getEnvelope()->with(
            new SentToFailureTransportStamp($event->getReceiverName()),
            new DelayStamp(0),
        );

        $failureSender->send($envelope);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', -100],
            WorkerMessageSkipEvent::class => ['onMessageSkip', -100],
        ];
    }
}

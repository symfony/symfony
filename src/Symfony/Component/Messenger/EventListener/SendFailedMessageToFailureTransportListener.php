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
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * Sends a rejected message to a "failure transport".
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @experimental in 4.3
 */
class SendFailedMessageToFailureTransportListener implements EventSubscriberInterface
{
    private $messageBus;
    private $failureSenderAlias;
    private $logger;

    public function __construct(MessageBusInterface $messageBus, string $failureSenderAlias, LoggerInterface $logger = null)
    {
        $this->messageBus = $messageBus;
        $this->failureSenderAlias = $failureSenderAlias;
        $this->logger = $logger;
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event)
    {
        if ($event->willRetry()) {
            return;
        }

        $envelope = $event->getEnvelope();

        // avoid re-sending to the failed sender
        if (null !== $envelope->last(SentToFailureTransportStamp::class)) {
            return;
        }

        // remove the received stamp so it's redelivered
        $throwable = $event->getThrowable();
        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getNestedExceptions()[0];
        }

        $flattenedException = class_exists(FlattenException::class) ? FlattenException::createFromThrowable($throwable) : null;
        $envelope = $envelope->withoutAll(ReceivedStamp::class)
            ->withoutAll(TransportMessageIdStamp::class)
            ->with(new SentToFailureTransportStamp($event->getReceiverName()))
            ->with(new DelayStamp(0))
            ->with(new RedeliveryStamp(0, $this->failureSenderAlias, $throwable->getMessage(), $flattenedException));

        if (null !== $this->logger) {
            $this->logger->info('Rejected message {class} will be sent to the failure transport {transport}.', [
                'class' => \get_class($envelope->getMessage()),
                'transport' => $this->failureSenderAlias,
            ]);
        }

        $this->messageBus->dispatch($envelope);
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', -100],
        ];
    }
}

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
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Sends a rejected message to a "failure transport".
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class SendFailedMessageToFailureTransportListener implements EventSubscriberInterface
{
    private ContainerInterface $failureSenders;
    private ?LoggerInterface $logger;
    private ?ContainerInterface $retryStrategyLocator;

    public function __construct(ContainerInterface $failureSenders, ?LoggerInterface $logger = null, ?ContainerInterface $retryStrategyLocator = null)
    {
        $this->failureSenders = $failureSenders;
        $this->logger = $logger;
        $this->retryStrategyLocator = $retryStrategyLocator;
    }

    /**
     * @return void
     */
    public function onMessageFailed(WorkerMessageFailedEvent $event)
    {
        if ($event->willRetry()) {
            return;
        }

        $originalTransportName = $event->getEnvelope()->last(ReceivedStamp::class)
            ?->getTransportName() ?? $event->getReceiverName();

        if (!$this->failureSenders->has($originalTransportName)) {
            return;
        }

        $failureSender = $this->failureSenders->get($originalTransportName);

        $envelope = $event->getEnvelope();

        $delay = $this->getRetryStrategyForTransport($event->getReceiverName())
            ?->getWaitingTime($envelope, $event->getThrowable()) ?? 0;

        $envelope = $envelope->with(
            new SentToFailureTransportStamp($originalTransportName),
            new DelayStamp($delay),
            new RedeliveryStamp(0)
        );

        $this->logger?->info('Rejected message {class} will be sent to the failure transport {transport} using {delay} ms delay.', [
            'class' => $envelope->getMessage()::class,
            'transport' => $failureSender::class,
            'delay' => $delay,
        ]);

        $failureSender->send($envelope);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', -100],
        ];
    }

    private function getRetryStrategyForTransport(string $transportName): ?RetryStrategyInterface
    {
        if (null === $this->retryStrategyLocator || !$this->retryStrategyLocator->has($transportName)) {
            return null;
        }

        return $this->retryStrategyLocator->get($transportName);
    }
}

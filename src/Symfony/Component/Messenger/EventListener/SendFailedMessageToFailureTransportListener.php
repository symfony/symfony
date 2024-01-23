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
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Sends a rejected message to a "failure transport".
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class SendFailedMessageToFailureTransportListener implements EventSubscriberInterface
{
    private $failureSenders;
    private $logger;

    /**
     * @param ContainerInterface $failureSenders
     */
    public function __construct($failureSenders, ?LoggerInterface $logger = null)
    {
        if (!$failureSenders instanceof ContainerInterface) {
            trigger_deprecation('symfony/messenger', '5.3', 'Passing a SenderInterface value as 1st argument to "%s()" is deprecated, pass a ServiceLocator instead.', __METHOD__);
        }

        $this->failureSenders = $failureSenders;
        $this->logger = $logger;
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event)
    {
        if ($event->willRetry()) {
            return;
        }

        if (!$this->hasFailureTransports($event)) {
            return;
        }

        $failureSender = $this->getFailureSender($event->getReceiverName());
        if (null === $failureSender) {
            return;
        }

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

        if (null !== $this->logger) {
            $this->logger->info('Rejected message {class} will be sent to the failure transport {transport}.', [
                'class' => \get_class($envelope->getMessage()),
                'transport' => \get_class($failureSender),
            ]);
        }

        $failureSender->send($envelope);
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', -100],
        ];
    }

    private function getFailureSender(string $receiverName): SenderInterface
    {
        if ($this->failureSenders instanceof SenderInterface) {
            return $this->failureSenders;
        }

        return $this->failureSenders->get($receiverName);
    }

    private function hasFailureTransports(WorkerMessageFailedEvent $event): bool
    {
        return ($this->failureSenders instanceof ContainerInterface && $this->failureSenders->has($event->getReceiverName())) || $this->failureSenders instanceof SenderInterface;
    }
}

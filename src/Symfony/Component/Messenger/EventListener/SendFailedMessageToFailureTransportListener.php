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
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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
    /**
     * @var ServiceLocator|SenderInterface
     */
    private $failureSender;
    private $logger;

    public function __construct($failureSender, LoggerInterface $logger = null)
    {
        if (!$failureSender instanceof ServiceLocator) {
            trigger_deprecation('symfony/messenger', '5.2', 'Passing failureTransports should now pass a ServiceLocator', __METHOD__);
        }

        $this->failureSender = $failureSender;
        $this->logger = $logger;
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event)
    {
        if ($event->willRetry()) {
            return;
        }

        $hasFailureTransports = $this->failureSender instanceof ServiceLocator && $this->failureSender->has($event->getReceiverName());
        if (null === !$hasFailureTransports) {
            return;
        }

        $envelope = $event->getEnvelope();

        // avoid re-sending to the failed sender
        if (null !== $envelope->last(SentToFailureTransportStamp::class)) {
            return;
        }

        $throwable = $event->getThrowable();
        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getNestedExceptions()[0];
        }

        $flattenedException = class_exists(FlattenException::class) ? FlattenException::createFromThrowable($throwable) : null;
        $envelope = $envelope->with(
            new SentToFailureTransportStamp($event->getReceiverName()),
            new DelayStamp(0),
            new RedeliveryStamp(0, $throwable->getMessage(), $flattenedException)
        );

        $failureSender = $this->getFailureSender($event->getReceiverName());
        if (null === $failureSender) {
            return;
        }

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

    private function getFailureSender(string $receiverName): ?SenderInterface
    {
        if ($this->failureSender instanceof ServiceLocator && $this->failureSender->has($receiverName)) {
            return $this->failureSender->get($receiverName);
        }

        return $this->failureSender;
    }
}

<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Failure;

use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

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
    private $logger;

    public function __construct(MessageBusInterface $messageBus, LoggerInterface $logger = null)
    {
        $this->messageBus = $messageBus;
        $this->logger = $logger;
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event)
    {
        if ($event->willRetry()) {
            return;
        }

        $throwable = $event->getThrowable();
        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getNestedExceptions()[0];
        }

        $flattenedException = \class_exists(FlattenException::class) ? FlattenException::createFromThrowable($throwable) : null;
        $failedMessage = new FailedMessage($event->getEnvelope(), $throwable->getMessage(), $flattenedException);

        if (null !== $this->logger) {
            $this->logger->info('Rejected message {class} will be sent to the failure transport.', [
                'class' => \get_class($event->getEnvelope()->getMessage()),
            ]);
        }

        $failedMessageEnvelope = new Envelope($failedMessage);
        // route this new message to the same bus as the original message
        if (null !== $busName = $this->getBusName($event->getEnvelope())) {
            $failedMessageEnvelope = $failedMessageEnvelope->with(new BusNameStamp($busName));
        }

        $this->messageBus->dispatch($failedMessageEnvelope);
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', -100],
        ];
    }

    private function getBusName(Envelope $envelope): ?string
    {
        /** @var BusNameStamp $busNameStamp */
        $busNameStamp = $envelope->last(BusNameStamp::class);

        return null === $busNameStamp ? null : $busNameStamp->getBusName();
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Inbox\InboxInterface;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Records the received messages in the inbox of their transport, and skips the handlers of the messages it recorded already.
 *
 * The inbox records a message before its handlers run, in the database transaction of the handlers.
 * The middleware that opens that transaction must run before this one.
 * A message retried from a failure transport is recorded for the transport it failed on.
 * A message without a MessageIdStamp is handled without being recorded.
 * So is a message that a batch handler buffers, since the batch handles the message later.
 */
final class InboxMiddleware implements MiddlewareInterface
{
    /**
     * @param ContainerInterface $inboxes               The inboxes, by name of the transport they record the messages of
     * @param string[]           $failureTransportNames The names of the failure transports
     */
    public function __construct(
        private ContainerInterface $inboxes,
        private array $failureTransportNames = [],
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $transportName = $this->getTransportName($envelope)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $context = ['class' => $envelope->getMessage()::class, 'transport' => $transportName];

        if (null === $messageId = $envelope->last(MessageIdStamp::class)?->getId()) {
            $this->logger?->warning('The inbox of the "{transport}" transport cannot record message {class} without an id', $context);

            return $stack->next()->handle($envelope, $stack);
        }

        /** @var InboxInterface $inbox */
        $inbox = $this->inboxes->get($transportName);

        if (!$inbox->record($transportName, $messageId)) {
            $this->logger?->info('Skipping message {class}, recorded already by the inbox of the "{transport}" transport', $context);

            return $envelope;
        }

        $envelope = $stack->next()->handle($envelope, $stack);

        if (null !== $envelope->last(NoAutoAckStamp::class)) {
            $inbox->forget($transportName, $messageId);
        }

        return $envelope;
    }

    /**
     * Returns the name of the transport whose inbox records the envelope, if any.
     *
     * @internal
     */
    public function getConsumer(Envelope $envelope): ?string
    {
        return null !== $envelope->last(MessageIdStamp::class) ? $this->getTransportName($envelope) : null;
    }

    private function getTransportName(Envelope $envelope): ?string
    {
        if (null === $transportName = $envelope->last(ReceivedStamp::class)?->getTransportName()) {
            return null;
        }

        // the inbox decided about each message of a batch when the batch handler buffered the message
        if (null !== $envelope->last(FlushBatchHandlersStamp::class)) {
            return null;
        }

        // the stamp is trusted only when the configuration says that the message comes from a failure transport
        if (\in_array($transportName, $this->failureTransportNames, true) && null !== $stamp = $envelope->last(SentToFailureTransportStamp::class)) {
            $transportName = $stamp->getOriginalReceiverName();
        }

        return $this->inboxes->has($transportName) ? $transportName : null;
    }
}

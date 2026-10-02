<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sync;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SyncMessageFailedEvent;
use Symfony\Component\Messenger\Event\SyncMessageRetryingEvent;
use Symfony\Component\Messenger\Exception\EnvelopeAwareExceptionInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Failure\FailureMessageDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Retry\RetryDecider;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Transport that immediately marks messages as received and dispatches for handling.
 *
 * When a retry strategy is given, a failed message is handled again right away, as many
 * times as the strategy allows. The delays of the strategy are not honored: the transport
 * runs inside the calling process and cannot wait between attempts.
 *
 * When a failure sender is given, a message that still fails is sent to it instead of throwing, and the returned envelope then carries a SentToFailureTransportStamp.
 * A message with a DispatchOnFailureStamp is sent to it only when its failure message cannot be dispatched: otherwise, the failure message is dispatched instead and the returned envelope carries the ErrorDetailsStamp of the failure.
 * The failure messages of the delayed messages that failed in their handlers while the message was handled are dispatched first.
 *
 * When an event dispatcher is given, a SyncMessageFailedEvent is dispatched for each
 * failed attempt and a SyncMessageRetryingEvent before each new attempt.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class SyncTransport implements TransportInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private ?RetryStrategyInterface $retryStrategy = null,
        private ?SenderInterface $failureSender = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param int $fetchSize
     */
    public function get(/* int $fetchSize = 1 */): iterable
    {
        throw new InvalidArgumentException('You cannot receive messages from the Messenger SyncTransport.');
    }

    public function ack(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call ack() on the Messenger SyncTransport.');
    }

    public function reject(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call reject() on the Messenger SyncTransport.');
    }

    public function send(Envelope $envelope): Envelope
    {
        /** @var SentStamp|null $sentStamp */
        $sentStamp = $envelope->last(SentStamp::class);
        $alias = null === $sentStamp ? 'sync' : ($sentStamp->getSenderAlias() ?: $sentStamp->getSenderClass());

        $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
        $context = ['class' => $envelope->getMessage()::class];

        if (!$envelope->last(TrustStamp::class) && !$envelope->last(ReceivedStamp::class)) {
            // the ReceivedStamp added below must not hide that the envelope was dispatched in this process
            $envelope = $envelope->with(TrustStamp::trusted());
        }

        while (true) {
            try {
                return $this->messageBus->dispatch($envelope->with($receivedStamp = new ReceivedStamp($alias)));
            } catch (\Throwable $e) {
                if ($e instanceof EnvelopeAwareExceptionInterface && null !== $handled = $e->getEnvelope()) {
                    // keep the stamps added while handling, so that a retry skips the handlers that succeeded,
                    // and the ReceivedStamp of a message that came from another transport, which tells it wasn't dispatched in this process
                    $envelope = new Envelope($handled->getMessage(), array_filter(array_merge(...array_values($handled->all())), static fn (StampInterface $stamp): bool => $stamp !== $receivedStamp));
                }

                // a forced retry is bounded by the strategy too: without a wait between
                // attempts, an unbounded retry would loop in place until the failure goes away
                $shouldRetry = $this->retryStrategy && false !== RetryDecider::decideFromException($e) && $this->retryStrategy->isRetryable($envelope, $e);

                $this->eventDispatcher?->dispatch(new SyncMessageFailedEvent($envelope, $alias, $e, $shouldRetry));

                if ($shouldRetry) {
                    ++$retryCount;
                    $envelope = $envelope->with(new RedeliveryStamp($retryCount));
                    $this->logger?->warning('Error thrown while handling message {class}. Retrying #{retryCount} immediately. Error: "{error}"', $context + ['retryCount' => $retryCount, 'error' => $e->getMessage(), 'exception' => $e]);
                    $this->eventDispatcher?->dispatch(new SyncMessageRetryingEvent($envelope, $alias));

                    continue;
                }

                if (null === $this->failureSender) {
                    throw $e;
                }

                $errorDetailsStamp = ErrorDetailsStamp::create($e);

                if (null !== $envelope->last(DispatchOnFailureStamp::class)) {
                    $this->logger?->critical('Error thrown while handling message {class}. Dispatching its failure message after {retryCount} retries. Error: "{error}"', $context + ['retryCount' => $retryCount, 'error' => $e->getMessage(), 'exception' => $e]);
                }

                if (FailureMessageDispatcher::dispatch($this->messageBus, $this->logger, $envelope, $e)) {
                    return $envelope->with($errorDetailsStamp);
                }

                $this->logger?->critical('Error thrown while handling message {class}. Sending to the failure transport after {retryCount} retries. Error: "{error}"', $context + ['retryCount' => $retryCount, 'error' => $e->getMessage(), 'exception' => $e]);

                return $this->failureSender->send($envelope->with(new SentToFailureTransportStamp($alias), new DelayStamp(0), new RedeliveryStamp(0), $errorDetailsStamp));
            }
        }
    }
}

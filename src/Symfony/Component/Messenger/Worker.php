<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRateLimitedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\EnvelopeAwareExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\RateLimiter\LimiterInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @final
 */
class Worker
{
    private bool $shouldStop = false;
    private WorkerMetadata $metadata;
    private array $acks = [];
    private ?\SplObjectStorage $unacks = null;
    /**
     * @var \SplObjectStorage<object, array{0: string, 1: Envelope}>
     */
    private \SplObjectStorage $keepalives;

    /**
     * @param ReceiverInterface[] $receivers Where the key is the transport name
     */
    public function __construct(
        private array $receivers,
        private MessageBusInterface $bus,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?LoggerInterface $logger = null,
        private ?array $rateLimiters = null,
        private ClockInterface $clock = new Clock(),
    ) {
        $this->metadata = new WorkerMetadata([
            'transportNames' => array_keys($receivers),
        ]);
        $this->keepalives = new \SplObjectStorage();
    }

    /**
     * Receive the messages and dispatch them to the bus.
     *
     * Valid options are:
     *  * sleep (default: 1000000): Time in microseconds to sleep after no messages are found
     *  * time_limit: The time limit in seconds the worker can handle new messages
     *  * queues: The queue names to consume from, instead of consuming from all queues. When this is used, all receivers must implement the QueueReceiverInterface
     */
    public function run(array $options = []): void
    {
        $options = array_merge([
            'sleep' => 1000000,
            'time_limit' => null,
        ], $options);
        $queueNames = $options['queues'] ?? null;
        $endTime = null !== $options['time_limit'] ? $this->clock->now()->format('U.u') + $options['time_limit'] : null;

        $this->metadata->set(['queueNames' => $queueNames]);

        $this->eventDispatcher?->dispatch(new WorkerStartedEvent($this));

        if ($queueNames) {
            // if queue names are specified, all receivers must implement the QueueReceiverInterface
            foreach ($this->receivers as $transportName => $receiver) {
                if (!$receiver instanceof QueueReceiverInterface) {
                    throw new RuntimeException(\sprintf('Receiver for "%s" does not implement "%s".', $transportName, QueueReceiverInterface::class));
                }
            }
        }

        while (!$this->shouldStop) {
            if (null !== $endTime && $this->clock->now()->format('U.u') >= $endTime) {
                $this->logger?->info('Worker stopped due to time limit of {timeLimit}s exceeded', ['timeLimit' => $options['time_limit']]);
                break;
            }

            $envelopeHandled = false;
            $envelopeHandledStart = $this->clock->now();
            foreach ($this->receivers as $transportName => $receiver) {
                if ($queueNames) {
                    $envelopes = $receiver->getFromQueues($queueNames);
                } else {
                    $envelopes = $receiver->get();
                }

                foreach ($envelopes as $envelope) {
                    $envelopeHandled = true;

                    if ($receiver instanceof KeepaliveReceiverInterface) {
                        $this->keepalives[$envelope->getMessage()] = [$transportName, $envelope];
                    }

                    $this->rateLimit($transportName);
                    $this->handleMessage($envelope, $transportName);
                    $this->eventDispatcher?->dispatch(new WorkerRunningEvent($this, false));

                    if ($this->shouldStop) {
                        break 2;
                    }
                }

                // after handling a single receiver, quit and start the loop again
                // this should prevent multiple lower priority receivers from
                // blocking too long before the higher priority are checked
                if ($envelopeHandled) {
                    break;
                }
            }

            if (!$envelopeHandled && $this->flush(false)) {
                continue;
            }

            if (!$this->flush(30.0) && !$envelopeHandled) {
                $this->eventDispatcher?->dispatch(new WorkerRunningEvent($this, true));

                if ($this->shouldStop) {
                    continue;
                }

                if (0 < $sleep = (int) ($options['sleep'] - 1e6 * ($this->clock->now()->format('U.u') - $envelopeHandledStart->format('U.u')))) {
                    if (null !== $endTime) {
                        $sleep = min($sleep, (int) ceil(1e6 * ($endTime - $this->clock->now()->format('U.u'))));
                    }

                    if (0 < $sleep) {
                        $this->clock->sleep($sleep / 1e6);
                    }
                }
            }
        }

        $this->flush(true);
        $this->eventDispatcher?->dispatch(new WorkerStoppedEvent($this));
    }

    private function handleMessage(Envelope $envelope, string $transportName): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $transportName);
        $this->eventDispatcher?->dispatch($event);
        $envelope = $event->getEnvelope();

        if (!$event->shouldHandle()) {
            return;
        }

        $acks = [];
        $dispatching = true;
        $batch = null;
        $combine = static function (array $acks) use (&$batch): ?array {
            return self::combineBatchAcks($batch, $acks);
        };
        $ack = function (Envelope $envelope, ?\Throwable $e = null) use ($transportName, &$acks, &$dispatching, $combine) {
            if ($dispatching) {
                $acks[] = [$transportName, $envelope, $e];
            } else {
                $this->acks[] = [$transportName, $envelope, $e, $combine];
            }
        };

        $heldAcks = \count($this->acks);
        try {
            $e = null;
            $envelope = $this->bus->dispatch($envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp(), new AckStamp($ack)));
        } catch (\Throwable $e) {
            $this->failHeldAcks($heldAcks);
        }

        if ($e instanceof HandlerFailedException && $e->getEnvelope()->last(NoAutoAckStamp::class)) {
            $acks = [];
            $e = $this->flushBatchHandlers($e, $acks);
        }

        // when the bus throws, its exception wins over the acks sent during the dispatch
        $dispatching = false;
        $acked = !$e && $acks;
        $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

        if ($acked || $noAutoAckStamp) {
            $batch = [$envelope->withoutAll(AckStamp::class), []];

            if ($batchAck = $combine($acks)) {
                $this->acks[] = $batchAck;
            }
        } else {
            $this->acks[] = [$transportName, $envelope, $e];
        }

        // the ack closure references this list, so emptying it frees the envelopes without waiting for the garbage collector
        $acks = [];

        if ($noAutoAckStamp) {
            $this->unacks ??= new \SplObjectStorage();
            foreach ($envelope->all(NoAutoAckStamp::class) as $stamp) {
                $this->unacks[$stamp->getHandlerDescriptor()->getBatchHandler()] = [$envelope, $transportName, $this->clock->now()->format('U.u')];
            }
        }

        $this->ack();
    }

    private function ack(): bool
    {
        $acks = $this->acks;
        $this->acks = [];

        foreach ($acks as $ack) {
            if (isset($ack[3]) && !$ack = $ack[3]([$ack])) {
                continue;
            }

            [$transportName, $envelope, $e] = $ack;
            $receiver = $this->receivers[$transportName];

            if (null !== $e) {
                if ($rejectFirst = $e instanceof RejectRedeliveredMessageException) {
                    // redelivered messages are rejected first so that continuous failures in an event listener or while
                    // publishing for retry does not cause infinite redelivery loops
                    unset($this->keepalives[$envelope->getMessage()]);
                    $receiver->reject($envelope);
                }

                if ($e instanceof EnvelopeAwareExceptionInterface && null !== $e->getEnvelope()) {
                    $envelope = $e->getEnvelope();
                }

                if ($e instanceof DelayedMessageHandlingException) {
                    // a retry must run the handlers again, so that they dispatch the delayed messages that failed again
                    $envelope = $envelope->withoutAll(HandledStamp::class);
                }

                $failedEvent = new WorkerMessageFailedEvent($envelope, $transportName, $e);

                $this->eventDispatcher?->dispatch($failedEvent);
                $envelope = $failedEvent->getEnvelope();

                if (!$rejectFirst) {
                    unset($this->keepalives[$envelope->getMessage()]);

                    if ($failedEvent->willRetry()) {
                        $receiver->ack($envelope);
                    } else {
                        $receiver->reject($envelope);
                    }
                }

                continue;
            }

            $handledEvent = new WorkerMessageHandledEvent($envelope, $transportName);
            $this->eventDispatcher?->dispatch($handledEvent);
            $envelope = $handledEvent->getEnvelope();

            if (null !== $this->logger) {
                $message = $envelope->getMessage();
                $context = [
                    'class' => $message::class,
                    'message_id' => $envelope->last(TransportMessageIdStamp::class)?->getId(),
                ];
                $this->logger->info('{class} was handled successfully (acknowledging to transport).', $context);
            }

            unset($this->keepalives[$envelope->getMessage()]);
            $receiver->ack($envelope);
        }

        return (bool) $acks;
    }

    private function rateLimit(string $transportName): void
    {
        if (!$this->rateLimiters) {
            return;
        }

        if (!\array_key_exists($transportName, $this->rateLimiters)) {
            return;
        }

        /** @var LimiterInterface $rateLimiter */
        $rateLimiter = $this->rateLimiters[$transportName]->create();
        if ($rateLimiter->consume()->isAccepted()) {
            return;
        }

        $this->logger?->info('Transport {transport} is being rate limited, waiting for token to become available...', ['transport' => $transportName]);

        $this->eventDispatcher?->dispatch(new WorkerRateLimitedEvent($rateLimiter, $transportName));
        $rateLimiter->reserve()->wait();
        $rateLimiter->consume();
    }

    private function flush(bool|float $force): bool
    {
        if (!$this->unacks) {
            return false;
        }

        if (\is_bool($force)) {
            $unacks = $this->unacks;
            $this->unacks = null;
        } else {
            $now = $this->clock->now()->format('U.u');
            $remaining = new \SplObjectStorage();
            $unacks = new \SplObjectStorage();

            foreach ($this->unacks as $handler) {
                if ($force <= $now - $this->unacks[$handler][2]) {
                    $unacks[$handler] = $this->unacks[$handler];
                } else {
                    $remaining[$handler] = $this->unacks[$handler];
                }
            }

            $this->unacks = $remaining->count() ? $remaining : null;
            $force = true;
        }

        if (!$unacks->count()) {
            return false;
        }

        foreach ($unacks as $handler) {
            [$envelope, $transportName] = $unacks[$handler];
            $heldAcks = \count($this->acks);
            try {
                $this->bus->dispatch($envelope->withoutAll(AckStamp::class)->with(new FlushBatchHandlersStamp($force)));
            } catch (\Throwable $e) {
                $this->failHeldAcks($heldAcks);
                $envelope = $envelope->withoutAll(NoAutoAckStamp::class);

                if ($e instanceof HandlerFailedException) {
                    // a retry must run the batch handlers whose flush failed
                    $failed = $e->getWrappedExceptions();
                    $envelope = $envelope->withoutAll(HandledStamp::class)->with(...array_filter($envelope->all(HandledStamp::class), static fn (HandledStamp $stamp) => !isset($failed[$stamp->getHandlerName()])));
                    $e = new HandlerFailedException($envelope, $failed);
                }

                if ($ackStamp = $envelope->last(AckStamp::class)) {
                    // the acks that the batch sent during the flush come first, and this failure is ignored when they already gave the message its outcome
                    $ackStamp->ack($envelope, $e);
                } else {
                    $this->acks[] = [$transportName, $envelope, $e];
                }
            }
        }

        return $this->ack();
    }

    public function stop(): void
    {
        $this->logger?->info('Stopping worker.', ['transport_names' => $this->metadata->getTransportNames()]);

        $this->shouldStop = true;
    }

    public function keepalive(?int $seconds): void
    {
        foreach ($this->keepalives as $message) {
            [$transportName, $envelope] = $this->keepalives[$message];

            if (!$this->receivers[$transportName] instanceof KeepaliveReceiverInterface) {
                throw new RuntimeException(\sprintf('Receiver for "%s" does not implement "%s".', $transportName, KeepaliveReceiverInterface::class));
            }

            $context = [
                'transport' => $transportName,
                'message_id' => $envelope->last(TransportMessageIdStamp::class)?->getId(),
            ];
            $this->logger?->info('Sending keepalive request.', $context);

            try {
                $this->receivers[$transportName]->keepalive($envelope, $seconds);
            } catch (\Throwable $e) {
                // the alarm is raised at any point of the program, so an error thrown from here
                // surfaces wherever the worker happened to be: it stops the worker, or it is read
                // as a failure of the message that was being handled at that moment
                $this->logger?->warning('Keepalive request failed: {error}', $context + ['error' => $e->getMessage(), 'exception' => $e]);
            }
        }
    }

    public function getMetadata(): WorkerMetadata
    {
        return $this->metadata;
    }

    /**
     * Combines the acks that the batch handlers of a message sent, once they all did.
     *
     * Returns null while some batch handlers didn't send theirs, or when the message already has its outcome.
     *
     * @param array{Envelope, list<array>}|null $batch The dispatched envelope and the acks received so far, null once the message has its outcome
     */
    private static function combineBatchAcks(?array &$batch, array $acks): ?array
    {
        if (null === $batch) {
            return null;
        }

        [$envelope, $received] = $batch;
        foreach ($acks as [$transportName, $ackEnvelope, $e]) {
            $received[] = [$transportName, $ackEnvelope, $e];
        }

        $pending = [];
        $handledStamps = [];
        foreach ($envelope->all(HandledStamp::class) as $stamp) {
            $handledStamps[$stamp->getHandlerName()] = $stamp;
        }
        foreach ($envelope->all(NoAutoAckStamp::class) as $stamp) {
            $name = $stamp->getHandlerDescriptor()->getName();
            $pending[$name] = true;
            unset($handledStamps[$name]);
        }

        $exceptions = [];
        foreach ($received as [, $ackEnvelope, $e]) {
            if ($e instanceof HandlerFailedException) {
                foreach ($e->getWrappedExceptions() as $name => $exception) {
                    unset($pending[$name]);
                    $exceptions[$name] = $exception;
                }
            } elseif (null !== $e) {
                // an error that isn't tied to a batch handler gives the message its outcome
                $pending = [];
                $exceptions[] = $e;
            } elseif ($stamp = $ackEnvelope->last(HandledStamp::class)) {
                $name = $stamp->getHandlerName();
                unset($pending[$name]);
                $handledStamps[$name] = $stamp;
            }
        }

        if ($pending) {
            $batch = [$envelope, $received];

            return null;
        }

        $batch = null;

        if (1 === \count($received)) {
            return $received[0];
        }

        $envelope = $envelope->withoutAll(NoAutoAckStamp::class)->withoutAll(HandledStamp::class)->with(...array_values($handledStamps));

        return [$received[0][0], $envelope, $exceptions ? new HandlerFailedException($envelope, $exceptions) : null];
    }

    /**
     * Turns the acks that messages sent during a failed dispatch into failures.
     *
     * Their batch was flushed during that dispatch, so what the batch did for them may have been undone.
     * The acks held from $offset on are the ones sent during that dispatch.
     */
    private function failHeldAcks(int $offset): void
    {
        foreach (\array_slice($this->acks, $offset, null, true) as $i => [$transportName, $envelope, $e]) {
            if (null !== $e || !$stamp = $envelope->last(HandledStamp::class)) {
                continue;
            }

            $stamps = $envelope->all(HandledStamp::class);
            array_pop($stamps);
            $envelope = $envelope->withoutAll(HandledStamp::class)->with(...$stamps);
            $e = new RuntimeException(\sprintf('The batch of "%s" was flushed during the dispatch of another message, and that dispatch failed.', $stamp->getHandlerName()));
            $this->acks[$i] = [$transportName, $envelope, new HandlerFailedException($envelope, [$stamp->getHandlerName() => $e])] + $this->acks[$i];
        }
    }

    /**
     * Flushes the batch handlers that still have a failed message in their batch, in a dispatch of their own.
     *
     * Their work for other messages then doesn't depend on the failed dispatch, and their outcome joins the failure.
     */
    private function flushBatchHandlers(HandlerFailedException $e, array &$acks): HandlerFailedException
    {
        $envelope = $e->getEnvelope();
        $exceptions = $e->getWrappedExceptions();

        $heldAcks = \count($this->acks);
        try {
            $this->bus->dispatch($envelope->withoutAll(AckStamp::class)->with(new FlushBatchHandlersStamp(true)));
        } catch (\Throwable $flushError) {
            // the failed flush may have undone what the batch did, so a retry runs it again
            $this->failHeldAcks($heldAcks);
            $acks = [];
            $exceptions += $flushError instanceof HandlerFailedException ? $flushError->getWrappedExceptions() : [$flushError];
        }

        $pending = array_map(static fn (NoAutoAckStamp $stamp) => $stamp->getHandlerDescriptor()->getName(), $envelope->all(NoAutoAckStamp::class));
        $stamps = array_filter($envelope->all(HandledStamp::class), static fn (HandledStamp $stamp) => !\in_array($stamp->getHandlerName(), $pending, true));

        foreach ($acks as [, $ackEnvelope, $ackError]) {
            if (null === $ackError) {
                $stamps[] = $ackEnvelope->last(HandledStamp::class);
            } elseif ($ackError instanceof HandlerFailedException) {
                $exceptions += $ackError->getWrappedExceptions();
            }
        }

        return new HandlerFailedException($envelope->withoutAll(HandledStamp::class)->with(...$stamps), $exceptions);
    }
}

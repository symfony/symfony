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
use Symfony\Component\Messenger\Exception\EnvelopeAwareExceptionInterface;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Execution\DeferredBatchMessageQueue;
use Symfony\Component\Messenger\Execution\MessageExecutionStrategyInterface;
use Symfony\Component\Messenger\Execution\SyncMessageExecutionStrategy;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
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
    private ?DeferredBatchMessageQueue $unacks = null;
    /**
     * @var \SplObjectStorage<object, array{0: string, 1: Envelope}>
     */
    private \SplObjectStorage $keepalives;

    private readonly MessageExecutionStrategyInterface $messageExecutionStrategy;

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
        ?MessageExecutionStrategyInterface $messageExecutionStrategy = null,
    ) {
        $this->metadata = new WorkerMetadata([
            'transportNames' => array_keys($receivers),
        ]);
        $this->keepalives = new \SplObjectStorage();
        $this->messageExecutionStrategy = $messageExecutionStrategy ?? new SyncMessageExecutionStrategy($this->bus, $this->enqueueAck(...));
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

        $this->eventDispatcher?->dispatch(new WorkerStartedEvent($this, null === $endTime ? null : (float) $endTime, $options['sleep']));

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
            $fetchSize = max(1, $options['fetch_size'] ?? 1);

            foreach ($this->receivers as $transportName => $receiver) {
                if ($queueNames) {
                    /** @var QueueReceiverInterface $receiver */
                    $envelopes = $receiver->getFromQueues($queueNames, $fetchSize);
                } else {
                    $envelopes = $receiver->get($fetchSize);
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
                        $sleep = min($sleep, (int) (1e6 * ($endTime - $this->clock->now()->format('U.u'))));
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

        $this->messageExecutionStrategy->execute(
            $envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp()),
            $transportName,
            $this->preAck(...),
        );
    }

    private function enqueueAck(string $transportName, Envelope $envelope, ?\Throwable $e = null): void
    {
        $this->acks[] = [$transportName, $envelope, $e];
    }

    private function preAck(Envelope $envelope, string $transportName, bool &$acked, ?\Throwable $e = null): void
    {
        $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

        if (!$acked && !$noAutoAckStamp) {
            $this->acks[] = [$transportName, $envelope, $e];
        } elseif ($noAutoAckStamp) {
            $this->unacks ??= new DeferredBatchMessageQueue();
            $this->unacks->add($noAutoAckStamp->getHandlerDescriptor()->getBatchHandler(), $transportName, $envelope->withoutAll(AckStamp::class), $acked, (float) $this->clock->now()->format('U.u'));
        }

        $this->ack();
    }

    private function ack(): bool
    {
        $acks = $this->acks;
        $this->acks = [];

        foreach ($acks as [$transportName, $envelope, $e]) {
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

                $failedEvent = new WorkerMessageFailedEvent($envelope, $transportName, $e);

                $this->eventDispatcher?->dispatch($failedEvent);
                $envelope = $failedEvent->getEnvelope();

                if (!$rejectFirst) {
                    unset($this->keepalives[$envelope->getMessage()]);
                    $receiver->reject($envelope);
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
        $flushed = $this->messageExecutionStrategy->flush($this->preAck(...), $force);

        if (!$this->unacks?->hasPending()) {
            return $flushed;
        }

        $unacks = $this->unacks->popFlushable($force, (float) $this->clock->now()->format('U.u'));

        if (!$unacks->count()) {
            return $flushed;
        }

        foreach ($unacks as $handler) {
            $deferredMessage = $unacks[$handler];
            $transportName = $deferredMessage->transportName;
            $envelope = $deferredMessage->envelope;
            try {
                $e = null;
                $this->bus->dispatch($envelope->with(new FlushBatchHandlersStamp(true === $force || !\is_bool($force))));
            } catch (\Throwable $e) {
                $envelope = $envelope->withoutAll(NoAutoAckStamp::class);
                $this->acks[] = [$transportName, $envelope, $e];
                continue;
            }

            $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

            if (!$deferredMessage->acked && !$noAutoAckStamp) {
                $this->acks[] = [$transportName, $envelope, $e];
            } elseif ($noAutoAckStamp) {
                $this->unacks ??= new DeferredBatchMessageQueue();
                $this->unacks->add($noAutoAckStamp->getHandlerDescriptor()->getBatchHandler(), $transportName, $envelope->withoutAll(AckStamp::class), $deferredMessage->acked, (float) $this->clock->now()->format('U.u'));
            }
        }

        return $this->ack() || $flushed;
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
            $receiver = $this->receivers[$transportName];

            if (!$receiver instanceof KeepaliveReceiverInterface) {
                throw new RuntimeException(\sprintf('Receiver for "%s" does not implement "%s".', $transportName, KeepaliveReceiverInterface::class));
            }

            $this->logger?->info('Sending keepalive request.', [
                'transport' => $transportName,
                'message_id' => $envelope->last(TransportMessageIdStamp::class)?->getId(),
            ]);
            $receiver->keepalive($envelope, $seconds);
        }
    }

    public function getMetadata(): WorkerMetadata
    {
        return $this->metadata;
    }
}

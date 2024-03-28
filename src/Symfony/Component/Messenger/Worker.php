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
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\FutureStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\RateLimiter\LimiterInterface;

use function Amp\Future\awaitAll;
use function Amp\Future\awaitAny;

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
    private \SplObjectStorage $unacks;

    private static $futures = [];

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
        $this->unacks = new \SplObjectStorage();
    }

    /**
     * Receive the messages and dispatch them to the bus.
     *
     * Valid options are:
     *  * sleep (default: 1000000): Time in microseconds to sleep after no messages are found
     *  * queues: The queue names to consume from, instead of consuming from all queues. When this is used, all receivers must implement the QueueReceiverInterface
     */
    public function run(array $options = []): void
    {
        $options = array_merge([
            'sleep' => 1000000,
        ], $options);
        $queueNames = $options['queues'] ?? null;

        $this->metadata->set(['queueNames' => $queueNames]);

        $this->eventDispatcher?->dispatch(new WorkerStartedEvent($this));

        if ($queueNames) {
            // if queue names are specified, all receivers must implement the QueueReceiverInterface
            foreach ($this->receivers as $transportName => $receiver) {
                if (!$receiver instanceof QueueReceiverInterface) {
                    throw new RuntimeException(sprintf('Receiver for "%s" does not implement "%s".', $transportName, QueueReceiverInterface::class));
                }
            }
        }

        while (!$this->shouldStop) {
            $envelopeHandled = false;
            $envelopeHandledStart = $this->clock->now();
            foreach ($this->receivers as $transportName => $receiver) {
                if ($queueNames) {
                    $envelopes = $receiver->getFromQueues($queueNames);
                } else {
                    $envelopes = $receiver->get();
                }

                if (!$envelopes) {
                    // flush
                    $this->handleFutures($transportName, $options['parallel-limit'] ?? 0);
                }

                foreach ($envelopes as $envelope) {
                    $envelopeHandled = true;

                    $this->rateLimit($transportName);
                    $this->handleMessage($envelope, $transportName, $options['parallel-limit'] ?? 0);
                    $this->eventDispatcher?->dispatch(new WorkerRunningEvent($this, false));

                    if ($this->shouldStop) {
                        break 2;
                    }
                }

                // after handling a single receiver, quit and start the loop again
                // this should prevent multiple lower priority receivers from
                // blocking too long before the higher priority are checked
                if ($envelopeHandled) {
                    gc_collect_cycles();

                    break;
                }
            }

            if (!$envelopeHandled && $this->flush(false)) {
                continue;
            }

            if (!$envelopeHandled) {
                $this->eventDispatcher?->dispatch(new WorkerRunningEvent($this, true));

                if (0 < $sleep = (int) ($options['sleep'] - 1e6 * ($this->clock->now()->format('U.u') - $envelopeHandledStart->format('U.u')))) {
                    $this->clock->sleep($sleep / 1e6);
                }
            }
        }

        $this->flush(true);
        $this->eventDispatcher?->dispatch(new WorkerStoppedEvent($this));
    }

    private function handleMessage(Envelope $envelope, string $transportName, int $parallelProcessesLimit): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $transportName);
        $this->eventDispatcher?->dispatch($event);
        $envelope = $event->getEnvelope();

        if (!$event->shouldHandle()) {
            return;
        }

        $acked = false;
        $busNameStamp = $envelope->last(BusNameStamp::class);

        try {
            $e = null;

            $envelope = $envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp());

            // "non concurrent" behaviour
            if (!$busNameStamp || 'parallel_bus' !== $busNameStamp->getBusName()) {
                $ack = function (Envelope $envelope, ?\Throwable $e = null) use ($transportName, &$acked) {
                    $acked = true;
                    $this->acks[] = [$transportName, $envelope, $e];
                };

                $envelope = $envelope->with(new AckStamp($ack));
            }

            $envelope = $this->bus->dispatch($envelope);

            // "non concurrent" behaviour
            if (null == $futureStamp = $envelope->last(FutureStamp::class)) {
                $this->preAck($envelope, $transportName, $acked, $e);

                return;
            }

            $envelope = $envelope->withoutStampsOfType(FutureStamp::class);
            self::$futures[] = [$futureStamp->getFuture(), $envelope];
        } catch (\Throwable $e) {
            $this->preAck($envelope, $transportName, $acked, $e);

            return;
        }

        if (\count(self::$futures) < $parallelProcessesLimit) {
            return;
        }

        $this->handleFutures($transportName, $parallelProcessesLimit);
    }

    private function preAck(Envelope $envelope, string $transportName, bool $acked, $e): void
    {
        $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

        if (!$acked && !$noAutoAckStamp) {
            $this->acks[] = [$transportName, $envelope, $e];
        } elseif ($noAutoAckStamp) {
            $this->unacks[$noAutoAckStamp->getHandlerDescriptor()->getBatchHandler()] = [$envelope->withoutAll(AckStamp::class), $transportName];
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
                    $receiver->reject($envelope);
                }

                if ($e instanceof HandlerFailedException || ($e instanceof DelayedMessageHandlingException && null !== $e->getEnvelope())) {
                    $envelope = $e->getEnvelope();
                }

                $failedEvent = new WorkerMessageFailedEvent($envelope, $transportName, $e);

                $this->eventDispatcher?->dispatch($failedEvent);
                $envelope = $failedEvent->getEnvelope();

                if (!$rejectFirst) {
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
    }

    private function flush(bool $force): bool
    {
        $unacks = $this->unacks;

        if (!$unacks->count()) {
            return false;
        }

        $this->unacks = new \SplObjectStorage();

        foreach ($unacks as $batchHandler) {
            [$envelope, $transportName] = $unacks[$batchHandler];
            try {
                $this->bus->dispatch($envelope->with(new FlushBatchHandlersStamp($force)));
                $envelope = $envelope->withoutAll(NoAutoAckStamp::class);
                unset($unacks[$batchHandler], $batchHandler);
            } catch (\Throwable $e) {
                $this->acks[] = [$transportName, $envelope, $e];
            }
        }

        return $this->ack();
    }

    public function stop(): void
    {
        $this->logger?->info('Stopping worker.', ['transport_names' => $this->metadata->getTransportNames()]);

        $this->shouldStop = true;
    }

    public function getMetadata(): WorkerMetadata
    {
        return $this->metadata;
    }

    public function handleFutures(string $transportName, $parallelProcessLimit): void
    {
        $toHandle = self::$futures;
        self::$futures = [];

        if (!$toHandle) {
            return;
        }

        $futuresReceived = [];
        $envelopesAssociated = [];

        foreach ($toHandle as $combo) {
            $futuresReceived[] = $combo[0];
            $envelopesAssociated[] = $combo[1];
        }

        [$errorsFromAwait, $executions] = awaitAll($futuresReceived);

        foreach ($errorsFromAwait as $index => $error) {
            try {
                $execution = $futuresReceived[$index]->await();
                $envelope = $execution->await();
            } catch (\Throwable $e) {
                $this->preAck($envelopesAssociated[$index]->withoutStampsOfType(FutureStamp::class), $transportName, false, $e);

                continue;
            }

            $this->preAck($envelope, $transportName, false, null);
        }

        $futures = array_map(fn ($execution) => $execution->getFuture(), $executions);

        $i = 0;

        while ($executions) {
            if ($i === $parallelProcessLimit) {
                break;
            }

            try {
                $envelope = awaitAny($futures);
                $futures = [];

                $executions = array_filter($executions, fn ($ex) => $ex->getTask()->getEnvelope()->last(TransportMessageIdStamp::class)->getId() !== $envelope->last(TransportMessageIdStamp::class)->getId());
                $futures = array_map(fn ($execution) => $execution->getFuture(), $executions);

                ++$i;
            } catch (\Throwable $e) {
                ++$i;
                continue;
            }

            $this->preAck($envelope, $transportName, false, null);
        }

        if (!$executions) {
            return;
        }

        foreach ($executions as $execution) {
            try {
                $envelope = $execution->await();
            } catch (\Throwable $e) {
                $envelope = $execution->getTask()->getEnvelope()->withoutStampsOfType(FutureStamp::class);
                $this->preAck($envelope, $transportName, false, $e);

                continue;
            }
            // It should not happen
            $this->preAck($envelope, $transportName, false, null);
        }
    }
}

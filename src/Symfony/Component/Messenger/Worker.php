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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\ChainedHandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 *
 * @final
 */
class Worker implements WorkerInterface
{
    private $receivers;
    private $bus;
    private $retryStrategies;
    private $eventDispatcher;
    private $logger;
    private $shouldStop = false;

    /**
     * @param ReceiverInterface[]      $receivers       Where the key will be used as the string "identifier"
     * @param RetryStrategyInterface[] $retryStrategies Retry strategies for each receiver (array keys must match)
     */
    public function __construct(array $receivers, MessageBusInterface $bus, $retryStrategies = [], EventDispatcherInterface $eventDispatcher = null, LoggerInterface $logger = null)
    {
        $this->receivers = $receivers;
        $this->bus = $bus;
        $this->retryStrategies = $retryStrategies;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Receive the messages and dispatch them to the bus.
     *
     * Valid options are:
     *  * sleep (default: 1000000): Time in microseconds to sleep after no messages are found
     */
    public function run(array $options = [], callable $onHandledCallback = null): void
    {
        $options = array_merge([
            'sleep' => 1000000,
        ], $options);

        if (\function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                $this->stop();
            });
        }

        $onHandled = function (?Envelope $envelope) use ($onHandledCallback) {
            if (\function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if (null !== $onHandledCallback) {
                $onHandledCallback($envelope);
            }
        };

        while (false === $this->shouldStop) {
            $envelopeHandled = false;
            foreach ($this->receivers as $receiverName => $receiver) {
                $envelopes = $receiver->get();

                foreach ($envelopes as $envelope) {
                    $envelopeHandled = true;

                    $this->handleMessage($envelope, $receiver, $receiverName, $this->retryStrategies[$receiverName] ?? null);
                    $onHandled($envelope);
                }

                // after handling a single receiver, quit and start the loop again
                // this should prevent multiple lower priority receivers from
                // blocking too long before the higher priority are checked
                if ($envelopeHandled) {
                    break;
                }
            }

            if (false === $envelopeHandled) {
                $onHandled(null);

                usleep($options['sleep']);
            }
        }
    }

    private function handleMessage(Envelope $envelope, ReceiverInterface $receiver, string $receiverName, ?RetryStrategyInterface $retryStrategy)
    {
        $this->dispatchEvent(new WorkerMessageReceivedEvent($envelope, $receiverName));

        $message = $envelope->getMessage();
        $context = [
            'message' => $message,
            'class' => \get_class($message),
        ];

        try {
            $envelope = $this->bus->dispatch($envelope->with(new ReceivedStamp()));
        } catch (\Throwable $throwable) {
            if ($throwable instanceof ChainedHandlerFailedException) {
                $envelope = $throwable->getEnvelope();
            }

            $shouldRetry = $this->shouldRetry($throwable, $envelope, $retryStrategy);

            $this->dispatchEvent(new WorkerMessageFailedEvent($envelope, $receiverName, $throwable, $shouldRetry));

            if ($shouldRetry) {
                if (null === $retryStrategy) {
                    // not logically allowed, but check just in case
                    throw new LogicException('Retrying is not supported without a retry strategy.');
                }

                $retryCount = $this->getRetryCount($envelope) + 1;
                if (null !== $this->logger) {
                    $this->logger->error('Retrying {class} - retry #{retryCount}.', $context + ['retryCount' => $retryCount, 'error' => $throwable]);
                }

                // add the delay and retry stamp info + remove ReceivedStamp
                $retryEnvelope = $envelope->with(new DelayStamp($retryStrategy->getWaitingTime($envelope)))
                    ->with(new RedeliveryStamp($retryCount, $this->getSenderAlias($envelope)))
                    ->withoutAll(ReceivedStamp::class);

                // re-send the message
                $this->bus->dispatch($retryEnvelope);
                // acknowledge the previous message has received
                $receiver->ack($envelope);
            } else {
                if (null !== $this->logger) {
                    $this->logger->critical('Rejecting {class} (removing from transport).', $context + ['error' => $throwable]);
                }

                $receiver->reject($envelope);
            }

            return;
        }

        $this->dispatchEvent(new WorkerMessageHandledEvent($envelope, $receiverName));

        if (null !== $this->logger) {
            $this->logger->info('{class} was handled successfully (acknowledging to transport).', $context);
        }

        $receiver->ack($envelope);
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function dispatchEvent($event)
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    private function shouldRetry(\Throwable $e, Envelope $envelope, ?RetryStrategyInterface $retryStrategy): bool
    {
        if ($e instanceof UnrecoverableMessageHandlingException) {
            return false;
        }

        if (null === $retryStrategy) {
            return false;
        }

        return $retryStrategy->isRetryable($envelope);
    }

    private function getRetryCount(Envelope $envelope): int
    {
        /** @var RedeliveryStamp|null $retryMessageStamp */
        $retryMessageStamp = $envelope->last(RedeliveryStamp::class);

        return $retryMessageStamp ? $retryMessageStamp->getRetryCount() : 0;
    }

    private function getSenderAlias(Envelope $envelope): ?string
    {
        /** @var SentStamp|null $sentStamp */
        $sentStamp = $envelope->last(SentStamp::class);

        return $sentStamp ? $sentStamp->getSenderAlias() : null;
    }
}

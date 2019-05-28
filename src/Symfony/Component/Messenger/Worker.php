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
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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
 * @experimental in 4.3
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
     * @param ReceiverInterface[]      $receivers       Where the key is the transport name
     * @param RetryStrategyInterface[] $retryStrategies Retry strategies for each receiver (array keys must match)
     */
    public function __construct(array $receivers, MessageBusInterface $bus, array $retryStrategies = [], EventDispatcherInterface $eventDispatcher = null, LoggerInterface $logger = null)
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
            foreach ($this->receivers as $transportName => $receiver) {
                $envelopes = $receiver->get();

                foreach ($envelopes as $envelope) {
                    $envelopeHandled = true;

                    $this->handleMessage($envelope, $receiver, $transportName, $this->retryStrategies[$transportName] ?? null);
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

        $this->dispatchEvent(new WorkerStoppedEvent());
    }

    private function handleMessage(Envelope $envelope, ReceiverInterface $receiver, string $transportName, ?RetryStrategyInterface $retryStrategy): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $transportName);
        $this->dispatchEvent($event);

        if (!$event->shouldHandle()) {
            return;
        }

        $message = $envelope->getMessage();
        $context = [
            'message' => $message,
            'class' => \get_class($message),
        ];

        try {
            $envelope = $this->bus->dispatch($envelope->with(new ReceivedStamp($transportName)));
        } catch (\Throwable $throwable) {
            if ($throwable instanceof HandlerFailedException) {
                $envelope = $throwable->getEnvelope();
            }

            $shouldRetry = $retryStrategy && $this->shouldRetry($throwable, $envelope, $retryStrategy);

            $this->dispatchEvent(new WorkerMessageFailedEvent($envelope, $transportName, $throwable, $shouldRetry));

            if ($shouldRetry) {
                $retryCount = $this->getRetryCount($envelope) + 1;
                if (null !== $this->logger) {
                    $this->logger->error('Retrying {class} - retry #{retryCount}.', $context + ['retryCount' => $retryCount, 'error' => $throwable]);
                }

                // add the delay and retry stamp info + remove ReceivedStamp
                $retryEnvelope = $envelope->with(new DelayStamp($retryStrategy->getWaitingTime($envelope)))
                    ->with(new RedeliveryStamp($retryCount, $this->getSenderClassOrAlias($envelope)))
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

        $this->dispatchEvent(new WorkerMessageHandledEvent($envelope, $transportName));

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

    private function shouldRetry(\Throwable $e, Envelope $envelope, RetryStrategyInterface $retryStrategy): bool
    {
        if ($e instanceof UnrecoverableMessageHandlingException) {
            return false;
        }

        $sentStamp = $envelope->last(SentStamp::class);
        if (null === $sentStamp) {
            if (null !== $this->logger) {
                $this->logger->warning('Message will not be retried because the SentStamp is missing and so the target sender cannot be determined.');
            }

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

    private function getSenderClassOrAlias(Envelope $envelope): string
    {
        /** @var SentStamp|null $sentStamp */
        $sentStamp = $envelope->last(SentStamp::class);

        if (null === $sentStamp) {
            // should not happen, because of the check in shouldRetry()
            throw new LogicException('Could not find SentStamp.');
        }

        return $sentStamp->getSenderAlias() ?: $sentStamp->getSenderClass();
    }
}

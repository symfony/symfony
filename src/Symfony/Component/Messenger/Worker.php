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
use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
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
        $this->eventDispatcher = LegacyEventDispatcherProxy::decorate($eventDispatcher);
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

            $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
            if ($shouldRetry) {
                ++$retryCount;
                $delay = $retryStrategy->getWaitingTime($envelope);
                if (null !== $this->logger) {
                    $this->logger->error('Error thrown while handling message {class}. Dispatching for retry #{retryCount} using {delay} ms delay. Error: "{error}"', $context + ['retryCount' => $retryCount, 'delay' => $delay, 'error' => $throwable->getMessage(), 'exception' => $throwable]);
                }

                // add the delay and retry stamp info + remove ReceivedStamp
                $retryEnvelope = $envelope->with(new DelayStamp($delay))
                    ->with(new RedeliveryStamp($retryCount, $transportName, $throwable->getMessage(), $this->flattenedException($throwable)))
                    ->withoutAll(ReceivedStamp::class);

                // re-send the message
                $this->bus->dispatch($retryEnvelope);
                // acknowledge the previous message has received
                $receiver->ack($envelope);
            } else {
                if (null !== $this->logger) {
                    $this->logger->critical('Error thrown while handling message {class}. Removing from transport after {retryCount} retries. Error: "{error}"', $context + ['retryCount' => $retryCount, 'error' => $throwable->getMessage(), 'exception' => $throwable]);
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

    private function dispatchEvent(object $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    private function shouldRetry(\Throwable $e, Envelope $envelope, RetryStrategyInterface $retryStrategy): bool
    {
        // if ALL nested Exceptions are an instance of UnrecoverableExceptionInterface we should not retry
        if ($e instanceof HandlerFailedException) {
            $shouldNotRetry = true;
            foreach ($e->getNestedExceptions() as $nestedException) {
                if (!$nestedException instanceof UnrecoverableExceptionInterface) {
                    $shouldNotRetry = false;
                    break;
                }
            }
            if ($shouldNotRetry) {
                return false;
            }
        }

        if ($e instanceof UnrecoverableExceptionInterface) {
            return false;
        }

        return $retryStrategy->isRetryable($envelope);
    }

    private function flattenedException(\Throwable $throwable): ?FlattenException
    {
        if (!class_exists(FlattenException::class)) {
            return null;
        }

        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getNestedExceptions()[0];
        }

        return FlattenException::createFromThrowable($throwable);
    }
}

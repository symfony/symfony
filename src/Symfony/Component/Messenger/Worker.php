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
class Worker
{
    private $receiver;
    private $bus;
    private $receiverName;
    private $retryStrategy;
    private $eventDispatcher;
    private $logger;

    public function __construct(ReceiverInterface $receiver, MessageBusInterface $bus, string $receiverName = null, RetryStrategyInterface $retryStrategy = null, EventDispatcherInterface $eventDispatcher = null, LoggerInterface $logger = null)
    {
        $this->receiver = $receiver;
        $this->bus = $bus;
        if (null === $receiverName) {
            @trigger_error(sprintf('Instantiating the "%s" class without passing a third argument is deprecated since Symfony 4.3.', __CLASS__), E_USER_DEPRECATED);

            $receiverName = 'unknown';
        }
        $this->receiverName = $receiverName;
        $this->retryStrategy = $retryStrategy;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Receive the messages and dispatch them to the bus.
     */
    public function run()
    {
        if (\function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                $this->receiver->stop();
            });
        }

        $this->receiver->receive(function (?Envelope $envelope) {
            if (null === $envelope) {
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                return;
            }

            $this->dispatchEvent(new WorkerMessageReceivedEvent($envelope, $this->receiverName));

            $message = $envelope->getMessage();
            $context = [
                'message' => $message,
                'class' => \get_class($message),
            ];

            try {
                $envelope = $this->bus->dispatch($envelope->with(new ReceivedStamp()));
            } catch (\Throwable $throwable) {
                $shouldRetry = $this->shouldRetry($throwable, $envelope);

                $this->dispatchEvent(new WorkerMessageFailedEvent($envelope, $this->receiverName, $throwable, $shouldRetry));

                if ($shouldRetry) {
                    if (null === $this->retryStrategy) {
                        // not logically allowed, but check just in case
                        throw new LogicException('Retrying is not supported without a retry strategy.');
                    }

                    $retryCount = $this->getRetryCount($envelope) + 1;
                    if (null !== $this->logger) {
                        $this->logger->error('Retrying {class} - retry #{retryCount}.', $context + ['retryCount' => $retryCount, 'error' => $throwable]);
                    }

                    // add the delay and retry stamp info + remove ReceivedStamp
                    $retryEnvelope = $envelope->with(new DelayStamp($this->retryStrategy->getWaitingTime($envelope)))
                        ->with(new RedeliveryStamp($retryCount, $this->getSenderAlias($envelope)))
                        ->withoutAll(ReceivedStamp::class);

                    // re-send the message
                    $this->bus->dispatch($retryEnvelope);
                    // acknowledge the previous message has received
                    $this->receiver->ack($envelope);
                } else {
                    if (null !== $this->logger) {
                        $this->logger->critical('Rejecting {class} (removing from transport).', $context + ['error' => $throwable]);
                    }

                    $this->receiver->reject($envelope);
                }

                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                return;
            }

            $this->dispatchEvent(new WorkerMessageHandledEvent($envelope, $this->receiverName));

            if (null !== $this->logger) {
                $this->logger->info('{class} was handled successfully (acknowledging to transport).', $context);
            }

            $this->receiver->ack($envelope);

            if (\function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        });
    }

    private function dispatchEvent($event)
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    private function shouldRetry(\Throwable $e, Envelope $envelope): bool
    {
        if ($e instanceof UnrecoverableMessageHandlingException) {
            return false;
        }

        if (null === $this->retryStrategy) {
            return false;
        }

        return $this->retryStrategy->isRetryable($envelope);
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

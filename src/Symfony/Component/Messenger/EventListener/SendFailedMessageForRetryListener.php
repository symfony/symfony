<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * @author Tobias Schultze <http://tobion.de>
 */
class SendFailedMessageForRetryListener implements EventSubscriberInterface
{
    private $sendersLocator;
    private $retryStrategyLocator;
    private $logger;

    public function __construct(ContainerInterface $sendersLocator, ContainerInterface $retryStrategyLocator, LoggerInterface $logger = null)
    {
        $this->sendersLocator = $sendersLocator;
        $this->retryStrategyLocator = $retryStrategyLocator;
        $this->logger = $logger;
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event)
    {
        $retryStrategy = $this->getRetryStrategyForTransport($event->getReceiverName());
        $envelope = $event->getEnvelope();
        $throwable = $event->getThrowable();

        $message = $envelope->getMessage();
        $context = [
            'message' => $message,
            'class' => \get_class($message),
        ];

        $shouldRetry = $retryStrategy && $this->shouldRetry($throwable, $envelope, $retryStrategy);

        $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
        if ($shouldRetry) {
            $event->setForRetry();

            ++$retryCount;

            $delay = $retryStrategy->getWaitingTime($envelope, $throwable);

            if (null !== $this->logger) {
                $this->logger->error('Error thrown while handling message {class}. Sending for retry #{retryCount} using {delay} ms delay. Error: "{error}"', $context + ['retryCount' => $retryCount, 'delay' => $delay, 'error' => $throwable->getMessage(), 'exception' => $throwable]);
            }

            // add the delay and retry stamp info
            $retryEnvelope = $envelope->with(new DelayStamp($delay), new RedeliveryStamp($retryCount));

            // re-send the message for retry
            $this->getSenderForTransport($event->getReceiverName())->send($retryEnvelope);
        } else {
            if (null !== $this->logger) {
                $this->logger->critical('Error thrown while handling message {class}. Removing from transport after {retryCount} retries. Error: "{error}"', $context + ['retryCount' => $retryCount, 'error' => $throwable->getMessage(), 'exception' => $throwable]);
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            // must have higher priority than SendFailedMessageToFailureTransportListener
            WorkerMessageFailedEvent::class => ['onMessageFailed', 100],
        ];
    }

    private function shouldRetry(\Throwable $e, Envelope $envelope, RetryStrategyInterface $retryStrategy): bool
    {
        if ($e instanceof RecoverableExceptionInterface) {
            return true;
        }

        // if one or more nested Exceptions is an instance of RecoverableExceptionInterface we should retry
        // if ALL nested Exceptions are an instance of UnrecoverableExceptionInterface we should not retry
        if ($e instanceof HandlerFailedException) {
            $shouldNotRetry = true;
            foreach ($e->getNestedExceptions() as $nestedException) {
                if ($nestedException instanceof RecoverableExceptionInterface) {
                    return true;
                }

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

        return $retryStrategy->isRetryable($envelope, $e);
    }

    private function getRetryStrategyForTransport(string $alias): ?RetryStrategyInterface
    {
        if ($this->retryStrategyLocator->has($alias)) {
            return $this->retryStrategyLocator->get($alias);
        }

        return null;
    }

    private function getSenderForTransport(string $alias): SenderInterface
    {
        if ($this->sendersLocator->has($alias)) {
            return $this->sendersLocator->get($alias);
        }

        throw new RuntimeException(sprintf('Could not find sender "%s" based on the same receiver to send the failed message to for retry.', $alias));
    }
}

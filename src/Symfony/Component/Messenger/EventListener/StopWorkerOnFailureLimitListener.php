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

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * @author Michel Hunziker <info@michelhunziker.com>
 */
class StopWorkerOnFailureLimitListener implements EventSubscriberInterface
{
    private int $failedMessages = 0;

    public function __construct(
        private int $maximumNumberOfFailures,
        private ?LoggerInterface $logger = null,
    ) {
        if ($maximumNumberOfFailures <= 0) {
            throw new InvalidArgumentException('Failure limit must be greater than zero.');
        }
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        ++$this->failedMessages;
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle() && $this->failedMessages >= $this->maximumNumberOfFailures) {
            $this->failedMessages = 0;
            $event->getWorker()->stop();

            $this->logger?->info('Worker stopped due to limit of {count} failed message(s) is reached', ['count' => $this->maximumNumberOfFailures]);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onMessageFailed',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}

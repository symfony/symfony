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
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * @author Simon Delicata <simon.delicata@free.fr>
 * @author Tobias Schultze <http://tobion.de>
 */
class StopWorkerOnTimeLimitListener implements EventSubscriberInterface
{
    private int $timeLimitInSeconds;
    private ?LoggerInterface $logger;
    private float $endTime = 0;

    public function __construct(int $timeLimitInSeconds, ?LoggerInterface $logger = null)
    {
        $this->timeLimitInSeconds = $timeLimitInSeconds;
        $this->logger = $logger;

        if ($timeLimitInSeconds <= 0) {
            throw new InvalidArgumentException('Time limit must be greater than zero.');
        }
    }

    public function onWorkerStarted(): void
    {
        $startTime = microtime(true);
        $this->endTime = $startTime + $this->timeLimitInSeconds;
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->endTime < microtime(true)) {
            $event->getWorker()->stop();
            $this->logger?->info('Worker stopped due to time limit of {timeLimit}s exceeded', ['timeLimit' => $this->timeLimitInSeconds]);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}

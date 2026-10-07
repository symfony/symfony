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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class StopWorkerOnRestartSignalListener implements EventSubscriberInterface
{
    public const RESTART_REQUESTED_TIMESTAMP_KEY = 'workers.restart_requested_timestamp';

    private float $workerStartedAt = 0;

    public function __construct(
        private CacheItemPoolInterface $cachePool,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onWorkerStarted(): void
    {
        $this->workerStartedAt = microtime(true);
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->shouldRestart($event->getWorker()->getMetadata()->getTransportNames())) {
            $event->getWorker()->stop();
            $this->logger?->info('Worker stopped because a restart was requested.');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }

    /**
     * @param string[] $transportNames
     */
    private function shouldRestart(array $transportNames): bool
    {
        $keys = [self::RESTART_REQUESTED_TIMESTAMP_KEY];

        foreach ($transportNames as $transportName) {
            $keys[] = self::RESTART_REQUESTED_TIMESTAMP_KEY.'.'.rawurlencode($transportName);
        }

        foreach ($this->cachePool->getItems($keys) as $cacheItem) {
            if ($cacheItem->isHit() && $this->workerStartedAt < $cacheItem->get()) {
                return true;
            }
        }

        return false;
    }
}

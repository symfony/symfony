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

    private CacheItemPoolInterface $cachePool;
    private ?LoggerInterface $logger;
    private float $workerStartedAt = 0;

    public function __construct(CacheItemPoolInterface $cachePool, ?LoggerInterface $logger = null)
    {
        $this->cachePool = $cachePool;
        $this->logger = $logger;
    }

    public function onWorkerStarted(): void
    {
        $this->workerStartedAt = microtime(true);
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->shouldRestart()) {
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

    private function shouldRestart(): bool
    {
        $cacheItem = $this->cachePool->getItem(self::RESTART_REQUESTED_TIMESTAMP_KEY);

        if (!$cacheItem->isHit()) {
            // no restart has ever been scheduled
            return false;
        }

        return $this->workerStartedAt < $cacheItem->get();
    }
}

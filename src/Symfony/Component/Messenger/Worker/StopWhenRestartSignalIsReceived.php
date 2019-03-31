<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Worker;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\WorkerInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @experimental in 4.3
 */
class StopWhenRestartSignalIsReceived implements WorkerInterface
{
    public const RESTART_REQUESTED_TIMESTAMP_KEY = 'workers.restart_requested_timestamp';

    private $decoratedWorker;
    private $cachePool;
    private $logger;

    public function __construct(WorkerInterface $decoratedWorker, CacheItemPoolInterface $cachePool, LoggerInterface $logger = null)
    {
        $this->decoratedWorker = $decoratedWorker;
        $this->cachePool = $cachePool;
        $this->logger = $logger;
    }

    public function run(array $options = [], callable $onHandledCallback = null): void
    {
        $workerStartedAt = microtime(true);

        $this->decoratedWorker->run($options, function (?Envelope $envelope) use ($onHandledCallback, $workerStartedAt) {
            if (null !== $onHandledCallback) {
                $onHandledCallback($envelope);
            }

            if ($this->shouldRestart($workerStartedAt)) {
                $this->stop();
                if (null !== $this->logger) {
                    $this->logger->info('Worker stopped because a restart was requested.');
                }
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedWorker->stop();
    }

    private function shouldRestart(float $workerStartedAt)
    {
        $cacheItem = $this->cachePool->getItem(self::RESTART_REQUESTED_TIMESTAMP_KEY);

        if (!$cacheItem->isHit()) {
            // no restart has ever been scheduled
            return false;
        }

        return $workerStartedAt < $cacheItem->get();
    }
}

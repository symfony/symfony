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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

/**
 * Stops the running workers after their current message, so that a process manager can restart them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class WorkerRestarter
{
    public function __construct(
        private CacheItemPoolInterface $restartSignalCachePool,
    ) {
    }

    /**
     * @param string ...$transportNames The transports whose workers to stop, or none to stop all workers
     */
    public function __invoke(string ...$transportNames): void
    {
        $keys = [StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY];

        if ($transportNames) {
            $keys = array_map(static fn (string $name): string => StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY.'.'.rawurlencode($name), $transportNames);
        }

        $now = microtime(true);

        foreach ($keys as $key) {
            $cacheItem = $this->restartSignalCachePool->getItem($key);
            $cacheItem->set($now);
            $this->restartSignalCachePool->save($cacheItem);
        }
    }
}

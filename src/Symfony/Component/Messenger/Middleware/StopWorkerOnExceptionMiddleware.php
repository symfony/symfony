<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Stop all workers when an exceptions is thrown.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class StopWorkerOnExceptionMiddleware
{
    private $exceptions;
    private $restartSignalCachePool;

    /**
     *
     * @param array $exceptions of fully qualified class names of exceptions
     */
    public function __construct(array $exceptions)
    {
        $this->exceptions = array_values($exceptions);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $e) {
            if (count($this->exceptions) === 0) {
                throw $e;
            }

            if (count($this->exceptions) === 1 && $this->exceptions[0] === '*') {
                $this->stopWorkers();
                throw $e;
            }

            foreach ($e->getNestedExceptions() as $exception) {
                if (in_array(get_class($exception), $this->exceptions)) {
                    $this->stopWorkers();
                    break;
                }
            }

            throw $e;
        }
    }

    private function stopWorkers(): void
    {
        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cacheItem->set(microtime(true));
        $this->restartSignalCachePool->save($cacheItem);
    }

    public function setRestartSignalCachePool(CacheItemPoolInterface $restartSignalCachePool): void
    {
        if ($this->restartSignalCachePool !== null) {
            throw new \RuntimeException('Cannot update restartSignalCachePool dependency');
        }

        $this->restartSignalCachePool = $restartSignalCachePool;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\Worker;

#[Group('time-sensitive')]
class StopWorkerOnRestartSignalListenerTest extends TestCase
{
    #[DataProvider('restartTimeProvider')]
    public function testWorkerStopsOnStartIfRestartInCache(?int $lastRestartTimeOffset, bool $shouldStop)
    {
        $cachePool = $this->createRestartInCachePool($lastRestartTimeOffset);

        $worker = $this->createMock(Worker::class);
        $worker->expects($shouldStop ? $this->once() : $this->never())->method('stop');
        $workerStartedEvent = new WorkerStartedEvent($worker);

        $stopOnSignalListener = new StopWorkerOnRestartSignalListener($cachePool);
        $stopOnSignalListener->onWorkerStarted($workerStartedEvent);
    }

    #[DataProvider('restartTimeProvider')]
    public function testWorkerStopsIfRestartInCache(?int $lastRestartTimeOffset, bool $shouldStop)
    {
        $cachePool = $this->createRestartInCachePool($lastRestartTimeOffset);

        $worker = $this->createMock(Worker::class);
        $worker->expects($shouldStop ? $this->atLeast(1) : $this->never())->method('stop');
        $workerStartedEvent = new WorkerStartedEvent($worker);
        $workerRunningEvent = new WorkerRunningEvent($worker, false);

        $stopOnSignalListener = new StopWorkerOnRestartSignalListener($cachePool);
        $stopOnSignalListener->onWorkerStarted($workerStartedEvent);
        $stopOnSignalListener->onWorkerRunning($workerRunningEvent);
    }

    public static function restartTimeProvider(): iterable
    {
        yield [null, false]; // no cached restart time, do not restart
        yield [+10, true]; // 10 seconds after starting, a restart was requested
        yield [-10, false]; // a restart was requested, but 10 seconds before we started
    }

    public function testWorkerDoesNotStopOnStartIfRestartNotInCache()
    {
        $cachePool = $this->createRestartNotInCachePool();

        $worker = $this->createMock(Worker::class);
        $worker->expects($this->never())->method('stop');
        $workerStartedEvent = new WorkerStartedEvent($worker);

        $stopOnSignalListener = new StopWorkerOnRestartSignalListener($cachePool);
        $stopOnSignalListener->onWorkerStarted($workerStartedEvent);
    }

    public function testWorkerDoesNotStopIfRestartNotInCache()
    {
        $cachePool = $this->createRestartNotInCachePool();

        $worker = $this->createMock(Worker::class);
        $worker->expects($this->never())->method('stop');
        $workerStartedEvent = new WorkerStartedEvent($worker);
        $workerRunningEvent = new WorkerRunningEvent($worker, false);

        $stopOnSignalListener = new StopWorkerOnRestartSignalListener($cachePool);
        $stopOnSignalListener->onWorkerStarted($workerStartedEvent);
        $stopOnSignalListener->onWorkerRunning($workerRunningEvent);
    }

    private function createRestartInCachePool(?int $value): CacheItemPoolInterface
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn(null === $value ? null : time() + $value);
        $cachePool->method('getItem')->willReturn($cacheItem);

        return $cachePool;
    }

    private function createRestartNotInCachePool(): CacheItemPoolInterface
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('get');
        $cachePool->method('getItem')->willReturn($cacheItem);

        return $cachePool;
    }
}

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

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\Worker;

/**
 * @group time-sensitive
 */
class StopWorkerOnRestartSignalListenerTest extends TestCase
{
    /**
     * @dataProvider restartTimeProvider
     */
    public function testWorkerStopsWhenMemoryLimitExceeded(?int $lastRestartTimeOffset, bool $shouldStop)
    {
        $cachePool = self::createMock(CacheItemPoolInterface::class);
        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(true);
        $cacheItem->expects(self::once())->method('get')->willReturn(null === $lastRestartTimeOffset ? null : time() + $lastRestartTimeOffset);
        $cachePool->expects(self::once())->method('getItem')->willReturn($cacheItem);

        $worker = self::createMock(Worker::class);
        $worker->expects($shouldStop ? self::once() : self::never())->method('stop');
        $event = new WorkerRunningEvent($worker, false);

        $stopOnSignalListener = new StopWorkerOnRestartSignalListener($cachePool);
        $stopOnSignalListener->onWorkerStarted();
        $stopOnSignalListener->onWorkerRunning($event);
    }

    public function restartTimeProvider()
    {
        yield [null, false]; // no cached restart time, do not restart
        yield [+10, true]; // 10 seconds after starting, a restart was requested
        yield [-10, false]; // a restart was requested, but 10 seconds before we started
    }

    public function testWorkerDoesNotStopIfRestartNotInCache()
    {
        $cachePool = self::createMock(CacheItemPoolInterface::class);
        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(false);
        $cacheItem->expects(self::never())->method('get');
        $cachePool->expects(self::once())->method('getItem')->willReturn($cacheItem);

        $worker = self::createMock(Worker::class);
        $worker->expects(self::never())->method('stop');
        $event = new WorkerRunningEvent($worker, false);

        $stopOnSignalListener = new StopWorkerOnRestartSignalListener($cachePool);
        $stopOnSignalListener->onWorkerStarted();
        $stopOnSignalListener->onWorkerRunning($event);
    }
}

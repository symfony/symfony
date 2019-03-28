<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Worker;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyWorker;
use Symfony\Component\Messenger\Worker\StopWhenRestartSignalIsReceived;

/**
 * @group time-sensitive
 */
class StopWhenRestartSignalIsReceivedTest extends TestCase
{
    /**
     * @dataProvider restartTimeProvider
     */
    public function testWorkerStopsWhenMemoryLimitExceeded(?int $lastRestartTimeOffset, bool $shouldStop)
    {
        $decoratedWorker = new DummyWorker([
            new Envelope(new \stdClass()),
        ]);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('isHIt')->willReturn(true);
        $cacheItem->expects($this->once())->method('get')->willReturn(null === $lastRestartTimeOffset ? null : time() + $lastRestartTimeOffset);
        $cachePool->expects($this->once())->method('getItem')->willReturn($cacheItem);

        $stopOnSignalWorker = new StopWhenRestartSignalIsReceived($decoratedWorker, $cachePool);
        $stopOnSignalWorker->run();

        $this->assertSame($shouldStop, $decoratedWorker->isStopped());
    }

    public function restartTimeProvider()
    {
        yield [null, false]; // no cached restart time, do not restart
        yield [+10, true]; // 10 seconds after starting, a restart was requested
        yield [-10, false]; // a restart was requested, but 10 seconds before we started
    }

    public function testWorkerDoesNotStopIfRestartNotInCache()
    {
        $decoratedWorker = new DummyWorker([
            new Envelope(new \stdClass()),
        ]);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('isHIt')->willReturn(false);
        $cacheItem->expects($this->never())->method('get');
        $cachePool->expects($this->once())->method('getItem')->willReturn($cacheItem);

        $stopOnSignalWorker = new StopWhenRestartSignalIsReceived($decoratedWorker, $cachePool);
        $stopOnSignalWorker->run();

        $this->assertFalse($decoratedWorker->isStopped());
    }
}

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
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Messenger\WorkerMetadata;

#[Group('time-sensitive')]
class StopWorkerOnRestartSignalListenerTest extends TestCase
{
    #[DataProvider('restartTimeProvider')]
    public function testWorkerStopsWhenARestartIsRequested(array $restartTimeOffsets, bool $shouldStop)
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getMetadata')->willReturn(new WorkerMetadata(['transportNames' => ['async', 'scheduler_default']]));
        $worker->expects($shouldStop ? $this->once() : $this->never())->method('stop');

        $stopOnSignalListener = new StopWorkerOnRestartSignalListener($this->createCachePool($restartTimeOffsets));
        $stopOnSignalListener->onWorkerStarted();
        $stopOnSignalListener->onWorkerRunning(new WorkerRunningEvent($worker, false));
    }

    public static function restartTimeProvider(): iterable
    {
        yield 'no restart requested' => [[], false];
        yield 'no cached restart time' => [['workers.restart_requested_timestamp' => null], false];
        yield 'all workers, after the start' => [['workers.restart_requested_timestamp' => 10], true];
        yield 'all workers, before the start' => [['workers.restart_requested_timestamp' => -10], false];
        yield 'a consumed transport, after the start' => [['workers.restart_requested_timestamp.scheduler_default' => 10], true];
        yield 'a consumed transport, before the start' => [['workers.restart_requested_timestamp.scheduler_default' => -10], false];
        yield 'another transport' => [['workers.restart_requested_timestamp.failed' => 10], false];
    }

    private function createCachePool(array $restartTimeOffsets): CacheItemPoolInterface
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->once())
            ->method('getItems')
            ->with(['workers.restart_requested_timestamp', 'workers.restart_requested_timestamp.async', 'workers.restart_requested_timestamp.scheduler_default'])
            ->willReturnCallback(function (array $keys) use ($restartTimeOffsets) {
                $items = [];
                foreach ($keys as $key) {
                    $item = $this->createStub(CacheItemInterface::class);
                    $item->method('isHit')->willReturn(\array_key_exists($key, $restartTimeOffsets));
                    $item->method('get')->willReturn(null === ($restartTimeOffsets[$key] ?? null) ? null : time() + $restartTimeOffsets[$key]);
                    $items[$key] = $item;
                }

                return $items;
            });

        return $cachePool;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheDataCollectorTest extends TestCase
{
    private const INSTANCE_NAME = 'test';

    public function testEmptyDataCollector()
    {
        $statistics = $this->getCacheDataCollectorStatisticsFromEvents([]);

        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['calls'], 'calls');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['reads'], 'reads');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['hits'], 'hits');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['misses'], 'misses');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['writes'], 'writes');
    }

    public function testOneEventDataCollector()
    {
        $traceableAdapterEvent = new \stdClass();
        $traceableAdapterEvent->name = 'getItem';
        $traceableAdapterEvent->start = 0;
        $traceableAdapterEvent->end = 0;
        $traceableAdapterEvent->hits = 0;

        $statistics = $this->getCacheDataCollectorStatisticsFromEvents([$traceableAdapterEvent]);

        $this->assertSame(1, $statistics[self::INSTANCE_NAME]['calls'], 'calls');
        $this->assertSame(1, $statistics[self::INSTANCE_NAME]['reads'], 'reads');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['hits'], 'hits');
        $this->assertSame(1, $statistics[self::INSTANCE_NAME]['misses'], 'misses');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['writes'], 'writes');
    }

    public function testHitedEventDataCollector()
    {
        $traceableAdapterEvent = new \stdClass();
        $traceableAdapterEvent->name = 'hasItem';
        $traceableAdapterEvent->start = 0;
        $traceableAdapterEvent->end = 0;
        $traceableAdapterEvent->hits = 0;
        $traceableAdapterEvent->misses = 0;
        $traceableAdapterEvent->result = ['foo' => false];

        $statistics = $this->getCacheDataCollectorStatisticsFromEvents([$traceableAdapterEvent]);

        $this->assertSame(1, $statistics[self::INSTANCE_NAME]['calls'], 'calls');
        $this->assertSame(1, $statistics[self::INSTANCE_NAME]['reads'], 'reads');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['hits'], 'hits');
        $this->assertSame(1, $statistics[self::INSTANCE_NAME]['misses'], 'misses');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['writes'], 'writes');
    }

    public function testSavedEventDataCollector()
    {
        $traceableAdapterEvent = new \stdClass();
        $traceableAdapterEvent->name = 'save';
        $traceableAdapterEvent->start = 0;
        $traceableAdapterEvent->end = 0;

        $statistics = $this->getCacheDataCollectorStatisticsFromEvents([$traceableAdapterEvent]);

        $this->assertSame(1, $statistics[self::INSTANCE_NAME]['calls'], 'calls');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['reads'], 'reads');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['hits'], 'hits');
        $this->assertSame(0, $statistics[self::INSTANCE_NAME]['misses'], 'misses');
        $this->assertSame(1, $statistics[self::INSTANCE_NAME]['writes'], 'writes');
    }

    public function testCollectBeforeEnd()
    {
        $adapter = new TraceableAdapter(new NullAdapter());

        $collector = new CacheDataCollector();
        $collector->addInstance(self::INSTANCE_NAME, $adapter);

        $adapter->get('foo', function () use ($collector) {
            $collector->collect(new Request(), new Response());

            return 123;
        });

        $stats = $collector->getStatistics();
        $this->assertGreaterThan(0, $stats[self::INSTANCE_NAME]['time']);
        $this->assertSame(0, $stats[self::INSTANCE_NAME]['hits'], 'hits');
        $this->assertSame(1, $stats[self::INSTANCE_NAME]['misses'], 'misses');
    }

    private function getCacheDataCollectorStatisticsFromEvents(array $traceableAdapterEvents)
    {
        $traceableAdapterMock = $this->createMock(TraceableAdapter::class);
        $traceableAdapterMock->method('getCalls')->willReturn($traceableAdapterEvents);

        $cacheDataCollector = new CacheDataCollector();
        $cacheDataCollector->addInstance(self::INSTANCE_NAME, $traceableAdapterMock);
        $cacheDataCollector->collect(new Request(), new Response());

        return $cacheDataCollector->getStatistics();
    }
}

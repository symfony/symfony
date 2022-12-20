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

        self::assertEquals($statistics[self::INSTANCE_NAME]['calls'], 0, 'calls');
        self::assertEquals($statistics[self::INSTANCE_NAME]['reads'], 0, 'reads');
        self::assertEquals($statistics[self::INSTANCE_NAME]['hits'], 0, 'hits');
        self::assertEquals($statistics[self::INSTANCE_NAME]['misses'], 0, 'misses');
        self::assertEquals($statistics[self::INSTANCE_NAME]['writes'], 0, 'writes');
    }

    public function testOneEventDataCollector()
    {
        $traceableAdapterEvent = new \stdClass();
        $traceableAdapterEvent->name = 'getItem';
        $traceableAdapterEvent->start = 0;
        $traceableAdapterEvent->end = 0;
        $traceableAdapterEvent->hits = 0;

        $statistics = $this->getCacheDataCollectorStatisticsFromEvents([$traceableAdapterEvent]);

        self::assertEquals($statistics[self::INSTANCE_NAME]['calls'], 1, 'calls');
        self::assertEquals($statistics[self::INSTANCE_NAME]['reads'], 1, 'reads');
        self::assertEquals($statistics[self::INSTANCE_NAME]['hits'], 0, 'hits');
        self::assertEquals($statistics[self::INSTANCE_NAME]['misses'], 1, 'misses');
        self::assertEquals($statistics[self::INSTANCE_NAME]['writes'], 0, 'writes');
    }

    public function testHitedEventDataCollector()
    {
        $traceableAdapterEvent = new \stdClass();
        $traceableAdapterEvent->name = 'hasItem';
        $traceableAdapterEvent->start = 0;
        $traceableAdapterEvent->end = 0;
        $traceableAdapterEvent->hits = 1;
        $traceableAdapterEvent->misses = 0;
        $traceableAdapterEvent->result = ['foo' => false];

        $statistics = $this->getCacheDataCollectorStatisticsFromEvents([$traceableAdapterEvent]);

        self::assertEquals($statistics[self::INSTANCE_NAME]['calls'], 1, 'calls');
        self::assertEquals($statistics[self::INSTANCE_NAME]['reads'], 1, 'reads');
        self::assertEquals($statistics[self::INSTANCE_NAME]['hits'], 1, 'hits');
        self::assertEquals($statistics[self::INSTANCE_NAME]['misses'], 0, 'misses');
        self::assertEquals($statistics[self::INSTANCE_NAME]['writes'], 0, 'writes');
    }

    public function testSavedEventDataCollector()
    {
        $traceableAdapterEvent = new \stdClass();
        $traceableAdapterEvent->name = 'save';
        $traceableAdapterEvent->start = 0;
        $traceableAdapterEvent->end = 0;

        $statistics = $this->getCacheDataCollectorStatisticsFromEvents([$traceableAdapterEvent]);

        self::assertEquals($statistics[self::INSTANCE_NAME]['calls'], 1, 'calls');
        self::assertEquals($statistics[self::INSTANCE_NAME]['reads'], 0, 'reads');
        self::assertEquals($statistics[self::INSTANCE_NAME]['hits'], 0, 'hits');
        self::assertEquals($statistics[self::INSTANCE_NAME]['misses'], 0, 'misses');
        self::assertEquals($statistics[self::INSTANCE_NAME]['writes'], 1, 'writes');
    }

    private function getCacheDataCollectorStatisticsFromEvents(array $traceableAdapterEvents)
    {
        $traceableAdapterMock = self::createMock(TraceableAdapter::class);
        $traceableAdapterMock->method('getCalls')->willReturn($traceableAdapterEvents);

        $cacheDataCollector = new CacheDataCollector();
        $cacheDataCollector->addInstance(self::INSTANCE_NAME, $traceableAdapterMock);
        $cacheDataCollector->collect(new Request(), new Response());

        return $cacheDataCollector->getStatistics();
    }
}

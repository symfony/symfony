<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * @group time-sensitive
 */
class TimeDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $c = new TimeDataCollector();

        $request = new Request();
        $request->server->set('REQUEST_TIME', 1);

        $c->collect($request, new Response());

        $this->assertEquals(0, $c->getStartTime());

        $request->server->set('REQUEST_TIME_FLOAT', 2);

        $c->collect($request, new Response());

        $this->assertEquals(2000, $c->getStartTime());

        $request = new Request();
        $c->collect($request, new Response());
        $this->assertEquals(0, $c->getStartTime());

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())->method('getStartTime')->willReturn(123456.0);

        $c = new TimeDataCollector($kernel);
        $request = new Request();
        $request->server->set('REQUEST_TIME', 1);

        $c->collect($request, new Response());
        $this->assertEquals(123456000, $c->getStartTime());
        $this->assertSame(class_exists(Stopwatch::class, false), $c->isStopwatchInstalled());
    }

    public function testReset()
    {
        $collector = new TimeDataCollector();

        // Fill Collector
        $request = Request::create('http://test.com/foo?bar=baz');
        $response = new Response('test');
        $collector->collect($request, $response);

        $collector->reset();

        $this->assertEquals([], $collector->getEvents());
        $this->assertEquals(0, $collector->getStartTime());
        $this->assertFalse($collector->isStopwatchInstalled());
    }

    public function testLateCollect()
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('test');

        $collector = new TimeDataCollector(null, $stopwatch);

        $request = new Request();
        $request->attributes->set('_stopwatch_token', '__root__');

        $collector->collect($request, new Response());
        $collector->lateCollect();

        $this->assertEquals(['test'], array_keys($collector->getEvents()));
    }

    public function testSetEvents()
    {
        $collector = new TimeDataCollector();

        $event = $this->createMock(StopwatchEvent::class);
        $event->expects($this->once())->method('ensureStopped');

        $events = [$event];

        $collector->setEvents($events);

        $this->assertCount(1, $collector->getEvents());
    }

    public function testGetDurationHasEvents()
    {
        $collector = new TimeDataCollector();

        $request = new Request();
        $request->server->set('REQUEST_TIME_FLOAT', 1);
        $collector->collect($request, new Response());

        $event = $this->createMock(StopwatchEvent::class);
        $event->expects($this->once())->method('getDuration')->willReturn(2000.0);
        $event->expects($this->once())->method('getOrigin')->willReturn(1000.0);
        $events = ['__section__' => $event];
        $collector->setEvents($events);

        $this->assertEquals(1000 + 2000 - 1000, $collector->getDuration());
    }

    public function testGetDurationNotEvents()
    {
        $collector = new TimeDataCollector();

        $this->assertEquals(0, $collector->getDuration());
    }

    public function testGetInitTimeNotEvents()
    {
        $collector = new TimeDataCollector();

        $this->assertEquals(0, $collector->getInitTime());
    }

    public function testGetInitTimeHasEvents()
    {
        $collector = new TimeDataCollector();

        $request = new Request();
        $request->server->set('REQUEST_TIME_FLOAT', 1);
        $collector->collect($request, new Response());

        $event = $this->createMock(StopwatchEvent::class);
        $event->expects($this->once())->method('getOrigin')->willReturn(2000.0);
        $events = ['__section__' => $event];
        $collector->setEvents($events);

        $this->assertEquals(2000 - 1000, $collector->getInitTime());
    }

    public function testGetName()
    {
        $collector = new TimeDataCollector();

        $this->assertEquals('time', $collector->getName());
    }
}

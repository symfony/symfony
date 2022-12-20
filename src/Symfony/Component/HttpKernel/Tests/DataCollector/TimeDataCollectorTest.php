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

        self::assertEquals(0, $c->getStartTime());

        $request->server->set('REQUEST_TIME_FLOAT', 2);

        $c->collect($request, new Response());

        self::assertEquals(2000, $c->getStartTime());

        $request = new Request();
        $c->collect($request, new Response());
        self::assertEquals(0, $c->getStartTime());

        $kernel = self::createMock(KernelInterface::class);
        $kernel->expects(self::once())->method('getStartTime')->willReturn(123456.0);

        $c = new TimeDataCollector($kernel);
        $request = new Request();
        $request->server->set('REQUEST_TIME', 1);

        $c->collect($request, new Response());
        self::assertEquals(123456000, $c->getStartTime());
        self::assertSame(class_exists(Stopwatch::class, false), $c->isStopwatchInstalled());
    }
}

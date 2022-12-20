<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * StopwatchEventTest.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @group time-sensitive
 */
class StopwatchEventTest extends TestCase
{
    private const DELTA = 37;

    public function testGetOrigin()
    {
        $event = new StopwatchEvent(12);
        self::assertEquals(12, $event->getOrigin());
    }

    public function testGetCategory()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        self::assertEquals('default', $event->getCategory());

        $event = new StopwatchEvent(microtime(true) * 1000, 'cat');
        self::assertEquals('cat', $event->getCategory());
    }

    public function testGetPeriods()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        self::assertEquals([], $event->getPeriods());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->stop();
        self::assertCount(1, $event->getPeriods());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->stop();
        $event->start();
        $event->stop();
        self::assertCount(2, $event->getPeriods());
    }

    public function testLap()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->lap();
        $event->stop();
        self::assertCount(2, $event->getPeriods());
    }

    public function testDuration()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(200000);
        $event->stop();
        self::assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->stop();
        usleep(50000);
        $event->start();
        usleep(100000);
        $event->stop();
        self::assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);
    }

    public function testDurationBeforeStop()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(200000);
        self::assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->stop();
        usleep(50000);
        $event->start();
        self::assertEqualsWithDelta(100, $event->getDuration(), self::DELTA);
        usleep(100000);
        self::assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);
    }

    public function testDurationWithMultipleStarts()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->start();
        usleep(100000);
        self::assertEqualsWithDelta(300, $event->getDuration(), self::DELTA);
        $event->stop();
        self::assertEqualsWithDelta(300, $event->getDuration(), self::DELTA);
        usleep(100000);
        self::assertEqualsWithDelta(400, $event->getDuration(), self::DELTA);
        $event->stop();
        self::assertEqualsWithDelta(400, $event->getDuration(), self::DELTA);
    }

    public function testStopWithoutStart()
    {
        self::expectException(\LogicException::class);
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->stop();
    }

    public function testIsStarted()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        self::assertTrue($event->isStarted());
    }

    public function testIsNotStarted()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        self::assertFalse($event->isStarted());
    }

    public function testEnsureStopped()
    {
        // this also test overlap between two periods
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->start();
        usleep(100000);
        $event->ensureStopped();
        self::assertEqualsWithDelta(300, $event->getDuration(), self::DELTA);
    }

    public function testStartTime()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        self::assertLessThanOrEqual(0.5, $event->getStartTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->stop();
        self::assertLessThanOrEqual(1, $event->getStartTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->stop();
        self::assertEqualsWithDelta(0, $event->getStartTime(), self::DELTA);
    }

    public function testStartTimeWhenStartedLater()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        usleep(100000);
        self::assertLessThanOrEqual(0.5, $event->getStartTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        usleep(100000);
        $event->start();
        $event->stop();
        self::assertLessThanOrEqual(101, $event->getStartTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        usleep(100000);
        $event->start();
        usleep(100000);
        self::assertEqualsWithDelta(100, $event->getStartTime(), self::DELTA);
        $event->stop();
        self::assertEqualsWithDelta(100, $event->getStartTime(), self::DELTA);
    }

    public function testHumanRepresentation()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        self::assertEquals('default/default: 0.00 MiB - 0 ms', (string) $event);
        $event->start();
        $event->stop();
        self::assertEquals(1, preg_match('/default: [0-9\.]+ MiB - [0-9]+ ms/', (string) $event));

        $event = new StopwatchEvent(microtime(true) * 1000, 'foo');
        self::assertEquals('foo/default: 0.00 MiB - 0 ms', (string) $event);

        $event = new StopwatchEvent(microtime(true) * 1000, 'foo', false, 'name');
        self::assertEquals('foo/name: 0.00 MiB - 0 ms', (string) $event);
    }

    public function testGetName()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        self::assertEquals('default', $event->getName());

        $event = new StopwatchEvent(microtime(true) * 1000, 'cat', false, 'name');
        self::assertEquals('name', $event->getName());
    }
}

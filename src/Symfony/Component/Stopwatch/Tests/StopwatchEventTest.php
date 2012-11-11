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

use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * StopwatchEventTest
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StopwatchEventTest extends \PHPUnit_Framework_TestCase
{
    public function testGetOrigin()
    {
        $event = new StopwatchEvent(12);
        $this->assertEquals(12, $event->getOrigin());
    }

    public function testGetCategory()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertEquals('default', $event->getCategory());

        $event = new StopwatchEvent(microtime(true) * 1000, 'cat');
        $this->assertEquals('cat', $event->getCategory());
    }

    public function testGetPeriods()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertEquals(array(), $event->getPeriods());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->stop();
        $this->assertCount(1, $event->getPeriods());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->stop();
        $event->start();
        $event->stop();
        $this->assertCount(2, $event->getPeriods());
    }

    public function testLap()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->lap();
        $event->stop();
        $this->assertCount(2, $event->getPeriods());
    }

    public function testDuration()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(20000);
        $event->stop();
        $total = $event->getDuration();
        $this->assertTrue($total >= 11 && $total <= 29, $total.' should be 20 (between 11 and 29)');

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(10000);
        $event->stop();
        $event->start();
        usleep(10000);
        $event->stop();
        $total = $event->getDuration();
        $this->assertTrue($total >= 11 && $total <= 29, $total.' should be 20 (between 11 and 29)');
    }

    /**
     * @expectedException \LogicException
     */
    public function testStopWithoutStart()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->stop();
    }

    public function testEnsureStopped()
    {
        // this also test overlap between two periods
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(10000);
        $event->start();
        usleep(10000);
        $event->ensureStopped();
        $total = $event->getDuration();
        $this->assertTrue($total >= 21 && $total <= 39, $total.' should be 30 (between 21 and 39)');
    }

    public function testStartTime()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertTrue($event->getStartTime() < 0.5);

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->stop();
        $this->assertTrue($event->getStartTime() < 1);

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(10000);
        $event->stop();
        $start = $event->getStartTime();
        $this->assertTrue($start >= 0 && $start <= 20);
    }

    public function testEndTime()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertEquals(0, $event->getEndTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $this->assertEquals(0, $event->getEndTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(10000);
        $event->stop();
        $event->start();
        usleep(10000);
        $event->stop();
        $end = $event->getEndTime();
        $this->assertTrue($end >= 11 && $end <= 29, $end.' should be 20 (between 11 and 29)');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidOriginThrowsAnException()
    {
        new StopwatchEvent("abc");
    }
}

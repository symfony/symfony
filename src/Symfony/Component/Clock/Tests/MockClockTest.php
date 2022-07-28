<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Clock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class MockClockTest extends TestCase
{
    public function testConstruct()
    {
        $clock = new MockClock();
        $this->assertSame('UTC', $clock->now()->getTimezone()->getName());

        $tz = 'Europe/Paris';
        $clock = new MockClock($tz);
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());

        $clock = new MockClock('now', $tz);
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());

        $clock = new MockClock('now', new \DateTimeZone($tz));
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());

        $now = new \DateTimeImmutable();
        $clock = new MockClock($now);
        $this->assertEquals($now, $clock->now());

        $clock = new MockClock('2022-06-20 00:00:00');
        $this->assertSame('2022-06-20 00:00:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testNow()
    {
        $before = new \DateTimeImmutable();
        $clock = new MockClock();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThan($before, $clock->now());
        $this->assertLessThan($after, $clock->now());

        $this->assertEquals($clock->now(), $clock->now());
        $this->assertNotSame($clock->now(), $clock->now());
    }

    public function testSleep()
    {
        $clock = new MockClock((new \DateTimeImmutable('@123.456'))->setTimezone(new \DateTimeZone('UTC')));
        $tz = $clock->now()->getTimezone()->getName();

        $clock->sleep(4.999);
        $this->assertSame('128.455000', $clock->now()->format('U.u'));
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());
    }

    public function testWithTimeZone()
    {
        $clock = new MockClock();
        $utcClock = $clock->withTimeZone('UTC');

        $this->assertNotSame($clock, $utcClock);
        $this->assertSame('UTC', $utcClock->now()->getTimezone()->getName());
    }
}

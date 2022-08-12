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
use Symfony\Component\Clock\NativeClock;

/**
 * @group time-sensitive
 */
class NativeClockTest extends TestCase
{
    public function testConstruct()
    {
        $clock = new NativeClock('UTC');
        $this->assertSame('UTC', $clock->now()->getTimezone()->getName());

        $tz = date_default_timezone_get();
        $clock = new NativeClock();
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());

        $clock = new NativeClock(new \DateTimeZone($tz));
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());
    }

    public function testNow()
    {
        $clock = new NativeClock();
        $before = microtime(true);
        usleep(10);
        $now = $clock->now();
        usleep(10);
        $after = microtime(true);

        $this->assertGreaterThan($before, (float) $now->format('U.u'));
        $this->assertLessThan($after, (float) $now->format('U.u'));
    }

    public function testSleep()
    {
        $clock = new NativeClock();
        $tz = $clock->now()->getTimezone()->getName();

        $before = microtime(true);
        $clock->sleep(1.5);
        $now = (float) $clock->now()->format('U.u');
        usleep(10);
        $after = microtime(true);

        $this->assertSame($before + 1.5, $now);
        $this->assertLessThan($after, $now);
        $this->assertLessThan(1.9, $now - $before);
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());
    }

    public function testWithTimeZone()
    {
        $clock = new NativeClock();
        $utcClock = $clock->withTimeZone('UTC');

        $this->assertNotSame($clock, $utcClock);
        $this->assertSame('UTC', $utcClock->now()->getTimezone()->getName());
    }
}

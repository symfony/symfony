<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * @author Dominic Tubach <dominic.tubach@to.com>
 *
 * @covers \Symfony\Bridge\PhpUnit\ClockMock
 */
#[CoversClass(ClockMock::class)]
class ClockMockTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClockMock::register(__CLASS__);
    }

    protected function setUp(): void
    {
        ClockMock::withClockMock(1234567890.125);
    }

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testTime()
    {
        $this->assertSame(1234567890, time());
    }

    public function testSleep()
    {
        sleep(2);
        $this->assertSame(1234567892, time());
    }

    public function testMicrotime()
    {
        $this->assertSame('0.12500000 1234567890', microtime());
    }

    public function testMicrotimeAsFloat()
    {
        $this->assertSame(1234567890.125, microtime(true));
    }

    public function testUsleep()
    {
        usleep(2);
        $this->assertSame(1234567890.125002, microtime(true));
    }

    public function testDate()
    {
        $this->assertSame('1234567890', date('U'));
    }

    public function testGmDate()
    {
        ClockMock::withClockMock(1555075769);

        $this->assertSame('1555075769', gmdate('U'));
    }

    public function testHrTime()
    {
        $this->assertSame([1234567890, 125000000], hrtime());
    }

    public function testHrTimeAsNumber()
    {
        $this->assertSame(1234567890125000000, hrtime(true));
    }

    public function testHrTimeAsNumberWithLeadingZerosInTheNanoseconds()
    {
        ClockMock::withClockMock(1234567890.0625);

        $this->assertSame([1234567890, 62500000], hrtime());
        $this->assertSame(1234567890062500000, hrtime(true));
    }

    public function testHrTimeWithoutClockMock()
    {
        ClockMock::withClockMock(false);

        $this->assertGreaterThan(0, hrtime()[0]);
        $this->assertGreaterThan(0, hrtime(true));
    }

    public function testStrToTime()
    {
        $this->assertSame(1234567890, strtotime('now'));
    }

    public function testFollowsTheGlobalMockClock()
    {
        Clock::set($clock = new MockClock('2020-01-01 00:00:00.5 UTC'));

        $this->assertSame(1577836800, time());
        $this->assertSame(1577836800.5, microtime(true));
        $this->assertSame('0.50000000 1577836800', microtime());
        $this->assertSame([1577836800, 500000000], hrtime());
        $this->assertSame(1577836800500000000, hrtime(true));
        $this->assertSame('2020-01-01 00:00:00', gmdate('Y-m-d H:i:s'));

        sleep(2);
        usleep(250000);
        $this->assertSame('2020-01-01 00:00:02.750000', $clock->now()->format('Y-m-d H:i:s.u'));

        $clock->modify('+1 hour');
        $this->assertSame(1577840402.75, microtime(true));

        Clock::set(new NativeClock());
        $this->assertSame(1234567890.125, microtime(true));
    }

    public function testIgnoresAGlobalMockClockSetBeforeEnablingTheMock()
    {
        Clock::set($clock = new MockClock('2020-01-01 00:00:00 UTC'));
        ClockMock::withClockMock(1234567890.125);

        sleep(2);

        $this->assertSame(1234567892, time());
        $this->assertSame('2020-01-01 00:00:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testIgnoresTheGlobalMockClockWhenDisabled()
    {
        ClockMock::withClockMock(false);
        Clock::set(new MockClock('2020-01-01 00:00:00 UTC'));

        $this->assertGreaterThan(1577836800, time());
    }
}

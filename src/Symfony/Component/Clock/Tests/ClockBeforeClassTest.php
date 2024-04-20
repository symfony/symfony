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
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class ClockBeforeClassTest extends TestCase
{
    use ClockSensitiveTrait;

    private static ?ClockInterface $clock = null;

    public static function setUpBeforeClass(): void
    {
        self::$clock = self::mockTime();
    }

    public static function tearDownAfterClass(): void
    {
        self::$clock = null;
    }

    public function testMockClock()
    {
        $this->assertInstanceOf(MockClock::class, self::$clock);
        $this->assertInstanceOf(NativeClock::class, Clock::get());

        $clock = self::mockTime();
        $this->assertInstanceOf(MockClock::class, Clock::get());
        $this->assertSame(Clock::get(), $clock);

        $this->assertNotSame($clock, self::$clock);

        self::restoreClockAfterTest();
        self::saveClockBeforeTest();

        $this->assertInstanceOf(MockClock::class, self::$clock);
        $this->assertInstanceOf(NativeClock::class, Clock::get());

        $clock = self::mockTime();
        $this->assertInstanceOf(MockClock::class, Clock::get());
        $this->assertSame(Clock::get(), $clock);

        $this->assertNotSame($clock, self::$clock);
    }
}

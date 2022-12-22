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

use function Symfony\Component\Clock\now;

class ClockTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testMockClock()
    {
        $this->assertInstanceOf(NativeClock::class, Clock::get());

        $clock = self::mockTime();
        $this->assertInstanceOf(MockClock::class, Clock::get());
        $this->assertSame(Clock::get(), $clock);
    }

    public function testNativeClock()
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, now());
        $this->assertInstanceOf(NativeClock::class, Clock::get());
    }

    public function testMockClockDisable()
    {
        $this->assertInstanceOf(NativeClock::class, Clock::get());

        $this->assertInstanceOf(MockClock::class, self::mockTime(true));
        $this->assertInstanceOf(NativeClock::class, self::mockTime(false));
    }

    public function testMockClockFreeze()
    {
        self::mockTime(new \DateTimeImmutable('2021-12-19'));

        $this->assertSame('2021-12-19', now()->format('Y-m-d'));

        self::mockTime('+1 days');
        $this->assertSame('2021-12-20', now()->format('Y-m-d'));
    }

    public function testPsrClock()
    {
        $psrClock = new class() implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@1234567');
            }
        };

        Clock::set($psrClock);

        $this->assertInstanceOf(Clock::class, Clock::get());

        $this->assertSame(1234567, now()->getTimestamp());

        $this->assertSame('UTC', Clock::get()->withTimeZone('UTC')->now()->getTimezone()->getName());
        $this->assertSame('Europe/Paris', Clock::get()->withTimeZone('Europe/Paris')->now()->getTimezone()->getName());

        Clock::get()->sleep(0.1);

        $this->assertSame(1234567, now()->getTimestamp());
    }
}

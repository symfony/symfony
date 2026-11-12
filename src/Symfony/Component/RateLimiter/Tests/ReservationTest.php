<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;

#[Group('time-sensitive')]
class ReservationTest extends TestCase
{
    public function testWaitDurationIsMeasuredWithTheClock()
    {
        $clock = new MockClock('@1000000000.25');
        $reservation = new Reservation(1000000005.75, $this->createRateLimit(), $clock);

        $this->assertEqualsWithDelta(5.5, $reservation->getWaitDuration(), 1e-6);

        $clock->sleep(2);
        $this->assertEqualsWithDelta(3.5, $reservation->getWaitDuration(), 1e-6);
    }

    public function testWaitDurationIsNeverNegative()
    {
        $clock = new MockClock('@1000000010');
        $reservation = new Reservation(1000000005.0, $this->createRateLimit(), $clock);

        $this->assertSame(0.0, (float) $reservation->getWaitDuration());
    }

    public function testWaitSleepsOnTheSymfonyClock()
    {
        $clock = new MockClock('@1000000000.25');
        $reservation = new Reservation(1000000005.75, $this->createRateLimit(), $clock);

        $reservation->wait();

        $this->assertSame('1000000005.750000', $clock->now()->format('U.u'));
        $this->assertEqualsWithDelta(0.0, $reservation->getWaitDuration(), 1e-6);
    }

    public function testWaitDoesNotSleepOnTheClockWhenTheTimeToActIsPassed()
    {
        $clock = new MockClock('@1000000010');
        $reservation = new Reservation(1000000005.0, $this->createRateLimit(), $clock);

        $reservation->wait();

        $this->assertSame('1000000010.000000', $clock->now()->format('U.u'));
    }

    public function testWaitMeasuresTheDelayFromAPsrClock()
    {
        ClockMock::register(Reservation::class);
        ClockMock::withClockMock(1500000000.0);
        $clock = new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@1000000000');
            }
        };
        $reservation = new Reservation(1000000003.0, $this->createRateLimit(), $clock);

        $this->assertEqualsWithDelta(3.0, $reservation->getWaitDuration(), 1e-6);

        $reservation->wait();

        $this->assertEqualsWithDelta(1500000003.0, microtime(true), 0.01);
    }

    public function testGetRateLimitAndTimeToAct()
    {
        $rateLimit = $this->createRateLimit();
        $reservation = new Reservation(1000000005.0, $rateLimit, new MockClock('@1000000000'));

        $this->assertSame($rateLimit, $reservation->getRateLimit());
        $this->assertSame(1000000005.0, $reservation->getTimeToAct());
    }

    private function createRateLimit(): RateLimit
    {
        return new RateLimit(1, new \DateTimeImmutable(), true, 1);
    }
}

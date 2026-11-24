<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @author Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DelayStampTest extends TestCase
{
    public function testDelayFor()
    {
        $stamp = DelayStamp::delayFor(\DateInterval::createFromDateString('30 seconds'));
        $this->assertSame(30000, $stamp->getDelay());
        $stamp = DelayStamp::delayFor(\DateInterval::createFromDateString('30 minutes'));
        $this->assertSame(1800000, $stamp->getDelay());
        $stamp = DelayStamp::delayFor(\DateInterval::createFromDateString('30 hours'));
        $this->assertSame(108000000, $stamp->getDelay());

        $stamp = DelayStamp::delayFor(\DateInterval::createFromDateString('-5 seconds'));
        $this->assertSame(-5000, $stamp->getDelay());
    }

    public function testDelayForWithFractionalSeconds()
    {
        $interval = new \DateInterval('PT0S');
        $interval->f = 0.25;

        $this->assertSame(250, DelayStamp::delayFor($interval)->getDelay());

        $interval->s = 1;
        $this->assertSame(1250, DelayStamp::delayFor($interval)->getDelay());

        $interval->invert = 1;
        $this->assertSame(-1250, DelayStamp::delayFor($interval)->getDelay());
    }

    public function testDelayForRoundsToMilliseconds()
    {
        $interval = new \DateInterval('PT0S');
        $interval->f = 0.0004;
        $this->assertSame(0, DelayStamp::delayFor($interval)->getDelay());

        $interval->f = 0.0005;
        $this->assertSame(1, DelayStamp::delayFor($interval)->getDelay());

        $interval->invert = 1;
        $this->assertSame(-1, DelayStamp::delayFor($interval)->getDelay());
    }

    #[Group('time-sensitive')]
    public function testDelayUntil()
    {
        ClockMock::withClockMock(1700000000.0);

        $stamp = DelayStamp::delayUntil(new \DateTimeImmutable('@'.(time() + 30)));
        $this->assertSame(30000, $stamp->getDelay());

        $stamp = DelayStamp::delayUntil(new \DateTimeImmutable('@'.(time() - 5)));
        $this->assertSame(-5000, $stamp->getDelay());
    }

    #[Group('time-sensitive')]
    public function testDelayUntilWithFractionalSeconds()
    {
        ClockMock::withClockMock(1700000000.25);

        $this->assertSame(29750, DelayStamp::delayUntil(new \DateTimeImmutable('@1700000030'))->getDelay());
        $this->assertSame(30250, DelayStamp::delayUntil(new \DateTimeImmutable('@1700000030.5'))->getDelay());
        $this->assertSame(-5250, DelayStamp::delayUntil(new \DateTimeImmutable('@1699999995'))->getDelay());
    }
}

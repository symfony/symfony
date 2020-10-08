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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @author Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DelayStampTest extends TestCase
{
    public function testSeconds()
    {
        $stamp = DelayStamp::delayForSeconds(30);
        $this->assertSame(30000, $stamp->getDelay());
    }

    public function testMinutes()
    {
        $stamp = DelayStamp::delayForMinutes(30);
        $this->assertSame(1800000, $stamp->getDelay());
    }

    public function testHours()
    {
        $stamp = DelayStamp::delayForHours(30);
        $this->assertSame(108000000, $stamp->getDelay());
    }

    public function testDelayUntil()
    {
        $untilDate = (new \DateTime())->modify('+30 minutes');
        $stamp = DelayStamp::delayUntil($untilDate);
        $this->assertSame(1800000, $stamp->getDelay());
    }

    public function testDelayUntilAcceptsOnlyFutureDates()
    {
        $untilDate = new \DateTime('1970-01-01T00:00:00Z');
        $this->expectException(InvalidArgumentException::class);
        DelayStamp::delayUntil($untilDate);
    }

    public function testDelayFor()
    {
        $stamp = DelayStamp::delayFor(30, DelayStamp::PERIOD_MINUTES);
        $this->assertSame(1800000, $stamp->getDelay());
    }

    public function testDelayForAcceptsOnlyPositiveUnits()
    {
        $this->expectException(InvalidArgumentException::class);
        DelayStamp::delayFor(-30, DelayStamp::PERIOD_MINUTES);
    }
}

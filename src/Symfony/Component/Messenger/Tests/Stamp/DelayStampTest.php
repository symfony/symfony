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

    public function testDelayUntil()
    {
        $stamp = DelayStamp::delayUntil(new \DateTimeImmutable('+30 seconds'));
        $this->assertSame(30000, $stamp->getDelay());

        $stamp = DelayStamp::delayUntil(new \DateTimeImmutable('-5 seconds'));
        $this->assertSame(-5000, $stamp->getDelay());
    }
}

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
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\MockClock;

class ClockAwareTraitTest extends TestCase
{
    public function testTrait()
    {
        $sut = new ClockAwareTestImplem();

        $this->assertInstanceOf(DatePoint::class, $sut->now());

        $clock = new MockClock();
        $sut = new $sut();
        $sut->setClock($clock);

        $ts = $sut->now()->getTimestamp();
        $this->assertEquals($clock->now(), $sut->now());
        $clock->sleep(1);
        $this->assertEquals($clock->now(), $sut->now());
        $this->assertSame(1.0, round($sut->now()->getTimestamp() - $ts, 1));
    }

    public function testCallSetClockMultipleTimes()
    {
        $sut = new ClockAwareTestImplem();

        $this->assertInstanceOf(DatePoint::class, $sut->now());

        $sut->setClock(new MockClock());

        $this->assertInstanceOf(DatePoint::class, $sut->now());
    }

    public function testCallNowBeforeSetClock()
    {
        $sut = new ClockAwareTestImplem();
        $sut->setClock(new MockClock());

        $this->assertInstanceOf(DatePoint::class, $sut->now());

        $sut->setClock(new MockClock());

        $this->assertInstanceOf(DatePoint::class, $sut->now());
    }
}

class ClockAwareTestImplem
{
    use ClockAwareTrait {
        now as public;
    }
}

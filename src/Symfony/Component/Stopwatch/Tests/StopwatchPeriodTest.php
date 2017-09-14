<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\StopwatchPeriod;

class StopwatchPeriodTest extends TestCase
{
    /**
     * @dataProvider provideTimeValues
     */
    public function testGetStartTime($start, $useMorePrecision, $expected)
    {
        $period = new StopwatchPeriod($start, $start, $useMorePrecision);
        $this->assertSame($expected, $period->getStartTime());
    }

    /**
     * @dataProvider provideTimeValues
     */
    public function testGetEndTime($end, $useMorePrecision, $expected)
    {
        $period = new StopwatchPeriod($end, $end, $useMorePrecision);
        $this->assertSame($expected, $period->getEndTime());
    }

    /**
     * @dataProvider provideDurationValues
     */
    public function testGetDuration($start, $end, $useMorePrecision, $duration)
    {
        $period = new StopwatchPeriod($start, $end, $useMorePrecision);
        $this->assertSame($duration, $period->getDuration());
    }

    public function provideTimeValues()
    {
        yield array(0, false, 0);
        yield array(0, true, 0.0);
        yield array(0.0, false, 0);
        yield array(0.0, true, 0.0);
        yield array(2.71, false, 2);
        yield array(2.71, true, 2.71);
    }

    public function provideDurationValues()
    {
        yield array(0, 0, false, 0);
        yield array(0, 0, true, 0.0);
        yield array(0.0, 0.0, false, 0);
        yield array(0.0, 0.0, true, 0.0);
        yield array(2, 3.14, false, 1);
        yield array(2, 3.14, true, 1.14);
        yield array(2.71, 3.14, false, 1);
        yield array(2.71, 3.14, true, 0.43);
    }
}

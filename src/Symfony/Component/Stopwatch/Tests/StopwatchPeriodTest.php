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
        $this->assertEqualsWithDelta($duration, $period->getDuration(), \PHP_FLOAT_EPSILON);
    }

    public static function provideTimeValues()
    {
        yield [0, false, 0];
        yield [0, true, 0.0];
        yield [0.0, false, 0];
        yield [0.0, true, 0.0];
        yield [2.71, false, 2];
        yield [2.71, true, 2.71];
    }

    public static function provideDurationValues()
    {
        yield [0, 0, false, 0];
        yield [0, 0, true, 0.0];
        yield [0.0, 0.0, false, 0];
        yield [0.0, 0.0, true, 0.0];
        yield [2, 3.14, false, 1];
        yield [2, 3.14, true, 1.1400000000000001];
        yield [2, 3.13, true, 1.13];
        yield [2.71, 3.14, false, 1];
        yield [2.71, 3.14, true, 0.43];
    }
}

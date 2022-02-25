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

use Generator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\StopwatchPeriod;

class StopwatchPeriodTest extends TestCase
{
    /**
     * @dataProvider provideTimeValues
     */
    public function testGetStartTime(int|float $start, bool $useMorePrecision, int|float $expected)
    {
        $period = new StopwatchPeriod($start, $start, $useMorePrecision);
        $this->assertSame($expected, $period->getStartTime());
    }

    /**
     * @dataProvider provideTimeValues
     */
    public function testGetEndTime(int|float $end, bool $useMorePrecision, int|float $expected)
    {
        $period = new StopwatchPeriod($end, $end, $useMorePrecision);
        $this->assertSame($expected, $period->getEndTime());
    }

    /**
     * @dataProvider provideDurationValues
     */
    public function testGetDuration(int|float $start, int|float $end, bool $useMorePrecision, int|float $duration)
    {
        $period = new StopwatchPeriod($start, $end, $useMorePrecision);
        $this->assertSame($duration, $period->getDuration());
    }

    public function provideTimeValues(): Generator
    {
        yield [0, false, 0];
        yield [0, true, 0.0];
        yield [0.0, false, 0];
        yield [0.0, true, 0.0];
        yield [2.71, false, 2];
        yield [2.71, true, 2.71];
    }

    public function provideDurationValues(): Generator
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

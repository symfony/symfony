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
    public function testGetStartTime($start, $end)
    {
        $period = new StopwatchPeriod($start, $end);
        $this->assertSame($start, $period->getStartTime());
    }

    /**
     * @dataProvider provideTimeValues
     */
    public function testGetEndTime($start, $end)
    {
        $period = new StopwatchPeriod($start, $end);
        $this->assertSame($end, $period->getEndTime());
    }

    /**
     * @dataProvider provideTimeValues
     */
    public function testGetDuration($start, $end, $duration)
    {
        $period = new StopwatchPeriod($start, $end);
        $this->assertSame($duration, $period->getDuration());
    }

    public function provideTimeValues()
    {
        yield [0, 0, 0];
        yield [0.0, 0.0, 0.0];
        yield [0.0, 2.7182, 2.7182];
        yield [3, 7, 4];
        yield [3, 3.14, 0.14];
        yield [3.10, 3.14, 0.04];
    }
}

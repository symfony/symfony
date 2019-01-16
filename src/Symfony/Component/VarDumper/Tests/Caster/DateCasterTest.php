<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\DateCaster;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class DateCasterTest extends TestCase
{
    use VarDumperTestTrait;

    /**
     * @dataProvider provideDateTimes
     */
    public function testDumpDateTime($time, $timezone, $xDate, $xTimestamp)
    {
        $date = new \DateTime($time, new \DateTimeZone($timezone));

        $xDump = <<<EODUMP
DateTime @$xTimestamp {
  date: $xDate
}
EODUMP;

        $this->assertDumpEquals($xDump, $date);
    }

    /**
     * @dataProvider provideDateTimes
     */
    public function testCastDateTime($time, $timezone, $xDate, $xTimestamp, $xInfos)
    {
        $stub = new Stub();
        $date = new \DateTime($time, new \DateTimeZone($timezone));
        $cast = DateCaster::castDateTime($date, ['foo' => 'bar'], $stub, false, 0);

        $xDump = <<<EODUMP
array:1 [
  "\\x00~\\x00date" => $xDate
]
EODUMP;

        $this->assertDumpEquals($xDump, $cast);

        $xDump = <<<EODUMP
Symfony\Component\VarDumper\Caster\ConstStub {
  +type: 1
  +class: "$xDate"
  +value: "%A$xInfos%A"
  +cut: 0
  +handle: 0
  +refCount: 0
  +position: 0
  +attr: []
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $cast["\0~\0date"]);
    }

    public function provideDateTimes()
    {
        return [
            ['2017-04-30 00:00:00.000000', 'Europe/Zurich', '2017-04-30 00:00:00.0 Europe/Zurich (+02:00)', 1493503200, 'Sunday, April 30, 2017%Afrom now%ADST On'],
            ['2017-12-31 00:00:00.000000', 'Europe/Zurich', '2017-12-31 00:00:00.0 Europe/Zurich (+01:00)', 1514674800, 'Sunday, December 31, 2017%Afrom now%ADST Off'],
            ['2017-04-30 00:00:00.000000', '+02:00', '2017-04-30 00:00:00.0 +02:00', 1493503200, 'Sunday, April 30, 2017%Afrom now'],

            ['2017-04-30 00:00:00.100000', '+00:00', '2017-04-30 00:00:00.100 +00:00', 1493510400, 'Sunday, April 30, 2017%Afrom now'],
            ['2017-04-30 00:00:00.120000', '+00:00', '2017-04-30 00:00:00.120 +00:00', 1493510400, 'Sunday, April 30, 2017%Afrom now'],
            ['2017-04-30 00:00:00.123000', '+00:00', '2017-04-30 00:00:00.123 +00:00', 1493510400, 'Sunday, April 30, 2017%Afrom now'],
            ['2017-04-30 00:00:00.123400', '+00:00', '2017-04-30 00:00:00.123400 +00:00', 1493510400, 'Sunday, April 30, 2017%Afrom now'],
            ['2017-04-30 00:00:00.123450', '+00:00', '2017-04-30 00:00:00.123450 +00:00', 1493510400, 'Sunday, April 30, 2017%Afrom now'],
            ['2017-04-30 00:00:00.123456', '+00:00', '2017-04-30 00:00:00.123456 +00:00', 1493510400, 'Sunday, April 30, 2017%Afrom now'],
        ];
    }

    /**
     * @dataProvider provideIntervals
     */
    public function testDumpInterval($intervalSpec, $ms, $invert, $expected)
    {
        if ($ms && \PHP_VERSION_ID >= 70200 && version_compare(PHP_VERSION, '7.2.0rc3', '<=')) {
            $this->markTestSkipped('Skipped on 7.2 before rc4 because of php bug #75354.');
        }

        $interval = $this->createInterval($intervalSpec, $ms, $invert);

        $xDump = <<<EODUMP
DateInterval {
  interval: $expected
%A}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $interval);
    }

    /**
     * @dataProvider provideIntervals
     */
    public function testDumpIntervalExcludingVerbosity($intervalSpec, $ms, $invert, $expected)
    {
        if ($ms && \PHP_VERSION_ID >= 70200 && version_compare(PHP_VERSION, '7.2.0rc3', '<=')) {
            $this->markTestSkipped('Skipped on 7.2 before rc4 because of php bug #75354.');
        }

        $interval = $this->createInterval($intervalSpec, $ms, $invert);

        $xDump = <<<EODUMP
DateInterval {
  interval: $expected
}
EODUMP;

        $this->assertDumpEquals($xDump, $interval, Caster::EXCLUDE_VERBOSE);
    }

    /**
     * @dataProvider provideIntervals
     */
    public function testCastInterval($intervalSpec, $ms, $invert, $xInterval, $xSeconds)
    {
        if ($ms && \PHP_VERSION_ID >= 70200 && version_compare(PHP_VERSION, '7.2.0rc3', '<=')) {
            $this->markTestSkipped('Skipped on 7.2 before rc4 because of php bug #75354.');
        }

        $interval = $this->createInterval($intervalSpec, $ms, $invert);
        $stub = new Stub();

        $cast = DateCaster::castInterval($interval, ['foo' => 'bar'], $stub, false, Caster::EXCLUDE_VERBOSE);

        $xDump = <<<EODUMP
array:1 [
  "\\x00~\\x00interval" => $xInterval
]
EODUMP;

        $this->assertDumpEquals($xDump, $cast);

        if (null === $xSeconds) {
            return;
        }

        $xDump = <<<EODUMP
Symfony\Component\VarDumper\Caster\ConstStub {
  +type: 1
  +class: "$xInterval"
  +value: "$xSeconds"
  +cut: 0
  +handle: 0
  +refCount: 0
  +position: 0
  +attr: []
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $cast["\0~\0interval"]);
    }

    public function provideIntervals()
    {
        return [
            ['PT0S', 0, 0, '0s', '0s'],
            ['PT0S', 0.1, 0, '+ 00:00:00.100', '%is'],
            ['PT1S', 0, 0, '+ 00:00:01.0', '%is'],
            ['PT2M', 0, 0, '+ 00:02:00.0', '%is'],
            ['PT3H', 0, 0, '+ 03:00:00.0', '%ss'],
            ['P4D', 0, 0, '+ 4d', '%ss'],
            ['P5M', 0, 0, '+ 5m', null],
            ['P6Y', 0, 0, '+ 6y', null],
            ['P1Y2M3DT4H5M6S', 0, 0, '+ 1y 2m 3d 04:05:06.0', null],
            ['PT1M60S', 0, 0, '+ 00:02:00.0', null],
            ['PT1H60M', 0, 0, '+ 02:00:00.0', null],
            ['P1DT24H', 0, 0, '+ 2d', null],
            ['P1M32D', 0, 0, '+ 1m 32d', null],

            ['PT0S', 0, 1, '0s', '0s'],
            ['PT0S', 0.1, 1, '- 00:00:00.100', '%is'],
            ['PT1S', 0, 1, '- 00:00:01.0', '%is'],
            ['PT2M', 0, 1, '- 00:02:00.0', '%is'],
            ['PT3H', 0, 1, '- 03:00:00.0', '%ss'],
            ['P4D', 0, 1, '- 4d', '%ss'],
            ['P5M', 0, 1, '- 5m', null],
            ['P6Y', 0, 1, '- 6y', null],
            ['P1Y2M3DT4H5M6S', 0, 1, '- 1y 2m 3d 04:05:06.0', null],
            ['PT1M60S', 0, 1, '- 00:02:00.0', null],
            ['PT1H60M', 0, 1, '- 02:00:00.0', null],
            ['P1DT24H', 0, 1, '- 2d', null],
            ['P1M32D', 0, 1, '- 1m 32d', null],
        ];
    }

    /**
     * @dataProvider provideTimeZones
     */
    public function testDumpTimeZone($timezone, $expected)
    {
        $timezone = new \DateTimeZone($timezone);

        $xDump = <<<EODUMP
DateTimeZone {
  timezone: $expected
%A}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $timezone);
    }

    /**
     * @dataProvider provideTimeZones
     */
    public function testDumpTimeZoneExcludingVerbosity($timezone, $expected)
    {
        $timezone = new \DateTimeZone($timezone);

        $xDump = <<<EODUMP
DateTimeZone {
  timezone: $expected
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $timezone, Caster::EXCLUDE_VERBOSE);
    }

    /**
     * @dataProvider provideTimeZones
     */
    public function testCastTimeZone($timezone, $xTimezone, $xRegion)
    {
        $timezone = new \DateTimeZone($timezone);
        $stub = new Stub();

        $cast = DateCaster::castTimeZone($timezone, ['foo' => 'bar'], $stub, false, Caster::EXCLUDE_VERBOSE);

        $xDump = <<<EODUMP
array:1 [
  "\\x00~\\x00timezone" => $xTimezone
]
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $cast);

        $xDump = <<<EODUMP
Symfony\Component\VarDumper\Caster\ConstStub {
  +type: 1
  +class: "$xTimezone"
  +value: "$xRegion"
  +cut: 0
  +handle: 0
  +refCount: 0
  +position: 0
  +attr: []
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $cast["\0~\0timezone"]);
    }

    public function provideTimeZones()
    {
        $xRegion = \extension_loaded('intl') ? '%s' : '';

        return [
            // type 1 (UTC offset)
            ['-12:00', '-12:00', ''],
            ['+00:00', '+00:00', ''],
            ['+14:00', '+14:00', ''],

            // type 2 (timezone abbreviation)
            ['GMT', '+00:00', ''],
            ['a', '+01:00', ''],
            ['b', '+02:00', ''],
            ['z', '+00:00', ''],

            // type 3 (timezone identifier)
            ['Africa/Tunis', 'Africa/Tunis (%s:00)', $xRegion],
            ['America/Panama', 'America/Panama (%s:00)', $xRegion],
            ['Asia/Jerusalem', 'Asia/Jerusalem (%s:00)', $xRegion],
            ['Atlantic/Canary', 'Atlantic/Canary (%s:00)', $xRegion],
            ['Australia/Perth', 'Australia/Perth (%s:00)', $xRegion],
            ['Europe/Zurich', 'Europe/Zurich (%s:00)', $xRegion],
            ['Pacific/Tahiti', 'Pacific/Tahiti (%s:00)', $xRegion],
        ];
    }

    /**
     * @dataProvider providePeriods
     */
    public function testDumpPeriod($start, $interval, $end, $options, $expected)
    {
        $p = new \DatePeriod(new \DateTime($start), new \DateInterval($interval), \is_int($end) ? $end : new \DateTime($end), $options);

        $xDump = <<<EODUMP
DatePeriod {
  period: $expected
%A}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $p);
    }

    /**
     * @dataProvider providePeriods
     */
    public function testCastPeriod($start, $interval, $end, $options, $xPeriod, $xDates)
    {
        $p = new \DatePeriod(new \DateTime($start), new \DateInterval($interval), \is_int($end) ? $end : new \DateTime($end), $options);
        $stub = new Stub();

        $cast = DateCaster::castPeriod($p, [], $stub, false, 0);

        $xDump = <<<EODUMP
array:1 [
  "\\x00~\\x00period" => $xPeriod
]
EODUMP;

        $this->assertDumpEquals($xDump, $cast);

        $xDump = <<<EODUMP
Symfony\Component\VarDumper\Caster\ConstStub {
  +type: 1
  +class: "$xPeriod"
  +value: "%A$xDates%A"
  +cut: 0
  +handle: 0
  +refCount: 0
  +position: 0
  +attr: []
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $cast["\0~\0period"]);
    }

    public function providePeriods()
    {
        $periods = [
            ['2017-01-01', 'P1D', '2017-01-03', 0, 'every + 1d, from 2017-01-01 00:00:00.0 (included) to 2017-01-03 00:00:00.0', '1) 2017-01-01%a2) 2017-01-02'],
            ['2017-01-01', 'P1D', 1, 0, 'every + 1d, from 2017-01-01 00:00:00.0 (included) recurring 2 time/s', '1) 2017-01-01%a2) 2017-01-02'],

            ['2017-01-01', 'P1D', '2017-01-04', 0, 'every + 1d, from 2017-01-01 00:00:00.0 (included) to 2017-01-04 00:00:00.0', '1) 2017-01-01%a2) 2017-01-02%a3) 2017-01-03'],
            ['2017-01-01', 'P1D', 2, 0, 'every + 1d, from 2017-01-01 00:00:00.0 (included) recurring 3 time/s', '1) 2017-01-01%a2) 2017-01-02%a3) 2017-01-03'],

            ['2017-01-01', 'P1D', '2017-01-05', 0, 'every + 1d, from 2017-01-01 00:00:00.0 (included) to 2017-01-05 00:00:00.0', '1) 2017-01-01%a2) 2017-01-02%a1 more'],
            ['2017-01-01', 'P1D', 3, 0, 'every + 1d, from 2017-01-01 00:00:00.0 (included) recurring 4 time/s', '1) 2017-01-01%a2) 2017-01-02%a3) 2017-01-03%a1 more'],

            ['2017-01-01', 'P1D', '2017-01-21', 0, 'every + 1d, from 2017-01-01 00:00:00.0 (included) to 2017-01-21 00:00:00.0', '1) 2017-01-01%a17 more'],
            ['2017-01-01', 'P1D', 19, 0, 'every + 1d, from 2017-01-01 00:00:00.0 (included) recurring 20 time/s', '1) 2017-01-01%a17 more'],

            ['2017-01-01 01:00:00', 'P1D', '2017-01-03 01:00:00', 0, 'every + 1d, from 2017-01-01 01:00:00.0 (included) to 2017-01-03 01:00:00.0', '1) 2017-01-01 01:00:00.0%a2) 2017-01-02 01:00:00.0'],
            ['2017-01-01 01:00:00', 'P1D', 1, 0, 'every + 1d, from 2017-01-01 01:00:00.0 (included) recurring 2 time/s', '1) 2017-01-01 01:00:00.0%a2) 2017-01-02 01:00:00.0'],

            ['2017-01-01', 'P1DT1H', '2017-01-03', 0, 'every + 1d 01:00:00.0, from 2017-01-01 00:00:00.0 (included) to 2017-01-03 00:00:00.0', '1) 2017-01-01 00:00:00.0%a2) 2017-01-02 01:00:00.0'],
            ['2017-01-01', 'P1DT1H', 1, 0, 'every + 1d 01:00:00.0, from 2017-01-01 00:00:00.0 (included) recurring 2 time/s', '1) 2017-01-01 00:00:00.0%a2) 2017-01-02 01:00:00.0'],

            ['2017-01-01', 'P1D', '2017-01-04', \DatePeriod::EXCLUDE_START_DATE, 'every + 1d, from 2017-01-01 00:00:00.0 (excluded) to 2017-01-04 00:00:00.0', '1) 2017-01-02%a2) 2017-01-03'],
            ['2017-01-01', 'P1D', 2, \DatePeriod::EXCLUDE_START_DATE, 'every + 1d, from 2017-01-01 00:00:00.0 (excluded) recurring 2 time/s', '1) 2017-01-02%a2) 2017-01-03'],
        ];

        if (\PHP_VERSION_ID < 70107) {
            array_walk($periods, function (&$i) { $i[5] = ''; });
        }

        return $periods;
    }

    private function createInterval($intervalSpec, $ms, $invert)
    {
        $interval = new \DateInterval($intervalSpec);
        $interval->f = $ms;
        $interval->invert = $invert;

        return $interval;
    }
}

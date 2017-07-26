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
    public function testDumpDateTime($time, $timezone, $expected)
    {
        if ((defined('HHVM_VERSION_ID') || PHP_VERSION_ID <= 50509) && preg_match('/[-+]\d{2}:\d{2}/', $timezone)) {
            $this->markTestSkipped('DateTimeZone GMT offsets are supported since 5.5.10. See https://github.com/facebook/hhvm/issues/5875 for HHVM.');
        }

        $date = new \DateTime($time, new \DateTimeZone($timezone));

        $xDump = <<<EODUMP
DateTime @1493503200 {
  date: $expected
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $date);
    }

    public function testCastDateTime()
    {
        $stub = new Stub();
        $date = new \DateTime('2017-08-30 00:00:00.000000', new \DateTimeZone('Europe/Zurich'));
        $cast = DateCaster::castDateTime($date, array('foo' => 'bar'), $stub, false, 0);

        $xDump = <<<'EODUMP'
array:1 [
  "\x00~\x00date" => 2017-08-30 00:00:00.000000 Europe/Zurich (+02:00)
]
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $cast);

        $xDump = <<<'EODUMP'
Symfony\Component\VarDumper\Caster\ConstStub {
  +type: 1
  +class: "2017-08-30 00:00:00.000000 Europe/Zurich (+02:00)"
  +value: """
    Wednesday, August 30, 2017\n
    +%a from now\n
    DST On
    """
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
        return array(
            array('2017-04-30 00:00:00.000000', 'Europe/Zurich', '2017-04-30 00:00:00.000000 Europe/Zurich (+02:00)'),
            array('2017-04-30 00:00:00.000000', '+02:00', '2017-04-30 00:00:00.000000 +02:00'),
        );
    }

    /**
     * @dataProvider provideIntervals
     */
    public function testDumpInterval($intervalSpec, $invert, $expected)
    {
        $interval = new \DateInterval($intervalSpec);
        $interval->invert = $invert;

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
    public function testDumpIntervalExcludingVerbosity($intervalSpec, $invert, $expected)
    {
        $interval = new \DateInterval($intervalSpec);
        $interval->invert = $invert;

        $xDump = <<<EODUMP
DateInterval {
  interval: $expected
}
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $interval, Caster::EXCLUDE_VERBOSE);
    }

    /**
     * @dataProvider provideIntervals
     */
    public function testCastInterval($intervalSpec, $invert, $xInterval, $xSeconds)
    {
        $interval = new \DateInterval($intervalSpec);
        $interval->invert = $invert;
        $stub = new Stub();

        $cast = DateCaster::castInterval($interval, array('foo' => 'bar'), $stub, false, Caster::EXCLUDE_VERBOSE);

        $xDump = <<<EODUMP
array:1 [
  "\\x00~\\x00interval" => $xInterval
]
EODUMP;

        $this->assertDumpMatchesFormat($xDump, $cast);

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
        $i = new \DateInterval('PT0S');
        $ms = \PHP_VERSION_ID >= 70100 && isset($i->f) ? '.000000' : '';

        return array(
            array('PT0S', 0, '0s', '0s'),
            array('PT1S', 0, '+ 00:00:01'.$ms, '1s'),
            array('PT2M', 0, '+ 00:02:00'.$ms, '120s'),
            array('PT3H', 0, '+ 03:00:00'.$ms, '10 800s'),
            array('P4D', 0, '+ 4d', '345 600s'),
            array('P5M', 0, '+ 5m', null),
            array('P6Y', 0, '+ 6y', null),
            array('P1Y2M3DT4H5M6S', 0, '+ 1y 2m 3d 04:05:06'.$ms, null),

            array('PT0S', 1, '0s', '0s'),
            array('PT1S', 1, '- 00:00:01'.$ms, '-1s'),
            array('PT2M', 1, '- 00:02:00'.$ms, '-120s'),
            array('PT3H', 1, '- 03:00:00'.$ms, '-10 800s'),
            array('P4D', 1, '- 4d', '-345 600s'),
            array('P5M', 1, '- 5m', null),
            array('P6Y', 1, '- 6y', null),
            array('P1Y2M3DT4H5M6S', 1, '- 1y 2m 3d 04:05:06'.$ms, null),
        );
    }

    /**
     * @dataProvider provideTimeZones
     */
    public function testDumpTimeZone($timezone, $expected)
    {
        if ((defined('HHVM_VERSION_ID') || PHP_VERSION_ID <= 50509) && !preg_match('/\w+\/\w+/', $timezone)) {
            $this->markTestSkipped('DateTimeZone GMT offsets are supported since 5.5.10. See https://github.com/facebook/hhvm/issues/5875 for HHVM.');
        }

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
        if ((defined('HHVM_VERSION_ID') || PHP_VERSION_ID <= 50509) && !preg_match('/\w+\/\w+/', $timezone)) {
            $this->markTestSkipped('DateTimeZone GMT offsets are supported since 5.5.10. See https://github.com/facebook/hhvm/issues/5875 for HHVM.');
        }

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
        if ((defined('HHVM_VERSION_ID') || PHP_VERSION_ID <= 50509) && !preg_match('/\w+\/\w+/', $timezone)) {
            $this->markTestSkipped('DateTimeZone GMT offsets are supported since 5.5.10. See https://github.com/facebook/hhvm/issues/5875 for HHVM.');
        }

        $timezone = new \DateTimeZone($timezone);
        $stub = new Stub();

        $cast = DateCaster::castTimeZone($timezone, array('foo' => 'bar'), $stub, false, Caster::EXCLUDE_VERBOSE);

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
        $xRegion = extension_loaded('intl') ? '%s' : '';

        return array(
            // type 1 (UTC offset)
            array('-12:00', '-12:00', ''),
            array('+00:00', '+00:00', ''),
            array('+14:00', '+14:00', ''),

            // type 2 (timezone abbreviation)
            array('GMT', '+00:00', ''),
            array('a', '+01:00', ''),
            array('b', '+02:00', ''),
            array('z', '+00:00', ''),

            // type 3 (timezone identifier)
            array('Africa/Tunis', 'Africa/Tunis (+01:00)', $xRegion),
            array('America/Panama', 'America/Panama (-05:00)', $xRegion),
            array('Asia/Jerusalem', 'Asia/Jerusalem (+03:00)', $xRegion),
            array('Atlantic/Canary', 'Atlantic/Canary (+01:00)', $xRegion),
            array('Australia/Perth', 'Australia/Perth (+08:00)', $xRegion),
            array('Europe/Zurich', 'Europe/Zurich (+02:00)', $xRegion),
            array('Pacific/Tahiti', 'Pacific/Tahiti (-10:00)', $xRegion),
        );
    }
}

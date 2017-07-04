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
  +type: "ref"
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
}

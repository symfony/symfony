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
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension mysqli
 *
 * @group integration
 */
class MysqliCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testNotConnected()
    {
        $driver = new \mysqli_driver();
        $driver->report_mode = 3;

        $xCast = <<<EODUMP
mysqli_driver {%A
  +reconnect: false
  +report_mode: 3
}
EODUMP;

        $this->assertDumpMatchesFormat($xCast, $driver);
    }
}

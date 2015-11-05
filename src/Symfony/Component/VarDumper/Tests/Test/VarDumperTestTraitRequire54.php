<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Test;

use Symfony\Component\VarDumper\Test\VarDumperTestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class VarDumperTestTraitTest extends VarDumperTestCase
{
    use VarDumperTestTrait;

    public function testItComparesLargeData()
    {
        $howMany = 700;
        $data = array_fill_keys(range(0, $howMany), array('a', 'b', 'c', 'd'));

        $expected = sprintf("array:%d [\n", $howMany + 1);
        for ($i = 0; $i <= $howMany; ++$i) {
            $expected .= <<<EODUMP
  $i => array:4 [
    0 => "a"
    1 => "b"
    2 => "c"
    3 => "d"
  ]\n
EODUMP;
        }
        $expected .= "]\n";

        $this->assertDumpEquals($expected, $data);
    }
}

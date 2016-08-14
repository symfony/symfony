<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Dumper;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\ToStringDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ToStringDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDecoratesDump()
    {
        $data = new Data(array());
        $stubDumper = $this->getMock('Symfony\Component\VarDumper\Dumper\AbstractDumper');
        $stubDumper->expects($this->exactly(2))->method('setOutput');

        $dumper = new ToStringDumper($stubDumper);
        $stubDumper->expects($this->once())->method('dump')->with($data);

        $dumper->dump($data);
    }

    public function testReturnsStreamContents()
    {
        $innerDumper = new CliDumper(null, null, CliDumper::DUMP_LIGHT_ARRAY);
        $cloner = new VarCloner();

        $dumper = new ToStringDumper($innerDumper);

        $this->assertEquals("[\n  \"a\" => 1\n  0 => 2\n]\n", $dumper->dump($cloner->cloneVar(array('a' => 1, 2))));
    }
}

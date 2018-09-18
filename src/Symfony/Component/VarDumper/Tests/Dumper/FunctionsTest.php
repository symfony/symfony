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

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;

class FunctionsTest extends TestCase
{
    public function testDumpReturnsFirstArg()
    {
        $this->setupVarDumper();

        $var1 = 'a';

        ob_start();
        $return = dump($var1);
        $out = ob_get_clean();

        $this->assertEquals($var1, $return);
    }

    public function testDumpReturnsAllArgsInArray()
    {
        $this->setupVarDumper();

        $var1 = 'a';
        $var2 = 'b';
        $var3 = 'c';

        ob_start();
        $return = dump($var1, $var2, $var3);
        $out = ob_get_clean();

        $this->assertEquals(array($var1, $var2, $var3), $return);
    }

    protected function setupVarDumper()
    {
        $cloner = new VarCloner();
        $dumper = new CliDumper('php://output');
        VarDumper::setHandler(function ($var) use ($cloner, $dumper) {
            $dumper->dump($cloner->cloneVar($var));
        });
    }
}

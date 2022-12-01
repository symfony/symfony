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
    public function testDumpWithoutArg()
    {
        $this->setupVarDumper();

        ob_start();
        $return = dump();
        ob_end_clean();

        $this->assertNull($return);
    }

    public function testDumpReturnsFirstArg()
    {
        $this->setupVarDumper();

        $var1 = 'a';

        ob_start();
        $return = dump($var1);
        ob_end_clean();

        $this->assertSame($var1, $return);
    }

    public function testDumpReturnsFirstNamedArgWithoutSectionName()
    {
        $this->setupVarDumper();

        $var1 = 'a';

        ob_start();
        $return = dump(first: $var1);
        ob_end_clean();

        $this->assertSame($var1, $return);
    }

    public function testDumpReturnsAllArgsInArray()
    {
        $this->setupVarDumper();

        $var1 = 'a';
        $var2 = 'b';
        $var3 = 'c';

        ob_start();
        $return = dump($var1, $var2, $var3);
        ob_end_clean();

        $this->assertSame([$var1, $var2, $var3], $return);
    }

    public function testDumpReturnsAllNamedArgsInArray()
    {
        $this->setupVarDumper();

        $var1 = 'a';
        $var2 = 'b';
        $var3 = 'c';

        ob_start();
        $return = dump($var1, second: $var2, third: $var3);
        ob_end_clean();

        $this->assertSame([$var1, 'second' => $var2, 'third' => $var3], $return);
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

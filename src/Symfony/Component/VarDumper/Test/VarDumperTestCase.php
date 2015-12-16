<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Test;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since version 2.8, to be removed in 3.0. Use the VarDumperTestTrait instead.
 */
abstract class VarDumperTestCase extends \PHPUnit_Framework_TestCase
{
    public function assertDumpEquals($dump, $data, $message = '')
    {
        $this->assertSame(rtrim($dump), $this->getDump($data), $message);
    }

    public function assertDumpMatchesFormat($dump, $data, $message = '')
    {
        $this->assertStringMatchesFormat(rtrim($dump), $this->getDump($data), $message);
    }

    protected function getDump($data)
    {
        $h = fopen('php://memory', 'r+b');
        $cloner = new VarCloner();
        $dumper = new CliDumper($h);
        $dumper->setColors(false);
        $dumper->dump($cloner->cloneVar($data)->withRefHandles(false));
        $data = stream_get_contents($h, -1, 0);
        fclose($h);

        return rtrim($data);
    }
}

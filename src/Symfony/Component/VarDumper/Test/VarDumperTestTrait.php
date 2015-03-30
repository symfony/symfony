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
 */
trait VarDumperTestTrait
{
    public function assertDumpEquals($dump, $data, $message = '')
    {
        $this->assertSame($dump, $this->getVarDumperDump($data), $message);
    }

    public function assertDumpMatchesFormat($dump, $data, $message = '')
    {
        $this->assertStringMatchesFormat($dump, $this->getVarDumperDump($data), $message);
    }

    private function getVarDumperDump($data)
    {
        $h = fopen('php://memory', 'r+b');
        $cloner = new VarCloner();
        $dumper = new CliDumper($h);
        $dumper->setColors(false);
        $dumper->dump($cloner->cloneVar($data)->withRefHandles(false));
        fseek($h, 0);
        $data = stream_get_contents($h);
        fclose($h);

        return $data;
    }
}

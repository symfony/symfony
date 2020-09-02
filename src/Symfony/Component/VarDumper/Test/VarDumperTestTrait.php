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
    public function assertDumpEquals($dump, $data, $filter = 0, $message = '')
    {
        if (\is_string($filter)) {
            @trigger_error(sprintf('The $message argument of the "%s()" method at the 3rd position is deprecated since Symfony 3.4 and will be moved at the 4th position in 4.0.', __METHOD__), \E_USER_DEPRECATED);
            $message = $filter;
            $filter = 0;
        }

        $this->assertSame(rtrim($dump), $this->getDump($data, null, $filter), $message);
    }

    public function assertDumpMatchesFormat($dump, $data, $filter = 0, $message = '')
    {
        if (\is_string($filter)) {
            @trigger_error(sprintf('The $message argument of the "%s()" method at the 3rd position is deprecated since Symfony 3.4 and will be moved at the 4th position in 4.0.', __METHOD__), \E_USER_DEPRECATED);
            $message = $filter;
            $filter = 0;
        }

        $this->assertStringMatchesFormat(rtrim($dump), $this->getDump($data, null, $filter), $message);
    }

    /**
     * @return string|null
     */
    protected function getDump($data, $key = null, $filter = 0)
    {
        $flags = getenv('DUMP_LIGHT_ARRAY') ? CliDumper::DUMP_LIGHT_ARRAY : 0;
        $flags |= getenv('DUMP_STRING_LENGTH') ? CliDumper::DUMP_STRING_LENGTH : 0;

        $cloner = new VarCloner();
        $cloner->setMaxItems(-1);
        $dumper = new CliDumper(null, null, $flags);
        $dumper->setColors(false);
        $data = $cloner->cloneVar($data, $filter)->withRefHandles(false);
        if (null !== $key && null === $data = $data->seek($key)) {
            return null;
        }

        return rtrim($dumper->dump($data, true));
    }
}

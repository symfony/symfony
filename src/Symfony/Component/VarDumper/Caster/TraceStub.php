<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Represents a backtrace as returned by debug_backtrace() or Exception->getTrace().
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TraceStub extends Stub
{
    public $srcContext;
    public $keepArgs;
    public $offset;
    public $length;

    public function __construct(array $trace, $srcContext = 1, $keepArgs = true, $offset = 0, $length = null)
    {
        $this->value = $trace;
        $this->srcContext = $srcContext;
        $this->keepArgs = $keepArgs;
        $this->offset = $offset;
        $this->length = $length;
    }
}

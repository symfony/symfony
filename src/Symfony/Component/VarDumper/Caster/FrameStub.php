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

/**
 * Represents a single backtrace frame as returned by debug_backtrace() or Exception->getTrace().
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FrameStub extends EnumStub
{
    public $srcContext;
    public $keepArgs;
    public $inTraceStub;

    public function __construct(array $trace, $srcContext = 1, $keepArgs = true, $inTraceStub = false)
    {
        $this->value = $trace;
        $this->srcContext = $srcContext;
        $this->keepArgs = $keepArgs;
        $this->inTraceStub = $inTraceStub;
    }
}

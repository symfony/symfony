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
    public bool $keepArgs;
    public int $sliceOffset;
    public ?int $sliceLength;
    public int $numberingOffset;

    public function __construct(array $trace, bool $keepArgs = true, int $sliceOffset = 0, ?int $sliceLength = null, int $numberingOffset = 0)
    {
        $this->value = $trace;
        $this->keepArgs = $keepArgs;
        $this->sliceOffset = $sliceOffset;
        $this->sliceLength = $sliceLength;
        $this->numberingOffset = $numberingOffset;
    }
}

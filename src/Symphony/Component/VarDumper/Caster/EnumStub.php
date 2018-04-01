<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\VarDumper\Caster;

use Symphony\Component\VarDumper\Cloner\Stub;

/**
 * Represents an enumeration of values.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnumStub extends Stub
{
    public $dumpKeys = true;

    public function __construct(array $values, bool $dumpKeys = true)
    {
        $this->value = $values;
        $this->dumpKeys = $dumpKeys;
    }
}

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
 * Represents a PHP constant and its value.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ConstStub extends Stub
{
    public function __construct(string $name, string|int|float|null $value = null)
    {
        $this->class = $name;
        $this->value = 1 < \func_num_args() ? $value : $name;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}

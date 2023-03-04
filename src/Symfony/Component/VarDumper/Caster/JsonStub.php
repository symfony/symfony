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
 * Represents a JSON resource.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class JsonStub extends Stub
{
    public function __construct(mixed $value)
    {
        $this->value = json_decode($value, true);
    }

    public function __toString(): string
    {
        return \is_array($this->value) ? json_encode($this->value) : $this->value;
    }
}

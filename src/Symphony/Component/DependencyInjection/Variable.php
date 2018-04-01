<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection;

/**
 * Represents a variable.
 *
 *     $var = new Variable('a');
 *
 * will be dumped as
 *
 *     $a
 *
 * by the PHP dumper.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Variable
{
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __toString()
    {
        return $this->name;
    }
}

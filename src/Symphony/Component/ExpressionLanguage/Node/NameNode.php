<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\ExpressionLanguage\Node;

use Symphony\Component\ExpressionLanguage\Compiler;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 *
 * @internal
 */
class NameNode extends Node
{
    public function __construct(string $name)
    {
        parent::__construct(
            array(),
            array('name' => $name)
        );
    }

    public function compile(Compiler $compiler)
    {
        $compiler->raw('$'.$this->attributes['name']);
    }

    public function evaluate($functions, $values)
    {
        return $values[$this->attributes['name']];
    }

    public function toArray()
    {
        return array($this->attributes['name']);
    }
}

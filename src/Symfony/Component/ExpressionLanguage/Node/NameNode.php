<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Node;

use Symfony\Component\ExpressionLanguage\Compiler;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class NameNode extends Node
{
    public function __construct($name)
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

    public function evaluate($functions, $values, $strict=true)
    {
        $key = $this->attributes['name'];
        if (!$strict && is_object($values)) {
            return $values->$key;
        }
        return $values[$key];
    }

    public function toArray()
    {
        return array($this->attributes['name']);
    }
}

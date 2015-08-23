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

class BetweenNode extends Node
{
    public function __construct(Node $value, Node $min, Node $max)
    {
        parent::__construct(
            array('value' => $value, 'min' => $min, 'max' => $max)
        );
    }

    public function compile(Compiler $compiler)
    {
        $compiler
            ->raw('(')
            ->compile($this->nodes['value'])
            ->raw(' >= ')
            ->compile($this->nodes['min'])
            ->raw(' && ')
            ->compile($this->nodes['value'])
            ->raw(' <= ')
            ->compile($this->nodes['max'])
            ->raw(')')
        ;
    }

    public function evaluate($functions, $values)
    {
        $value = $this->nodes['value']->evaluate($functions, $values);
        $min = $this->nodes['min']->evaluate($functions, $values);
        $max = $this->nodes['max']->evaluate($functions, $values);

        return ($value >= $min && $value <= $max);
    }
}

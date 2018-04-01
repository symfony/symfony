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
class ConditionalNode extends Node
{
    public function __construct(Node $expr1, Node $expr2, Node $expr3)
    {
        parent::__construct(
            array('expr1' => $expr1, 'expr2' => $expr2, 'expr3' => $expr3)
        );
    }

    public function compile(Compiler $compiler)
    {
        $compiler
            ->raw('((')
            ->compile($this->nodes['expr1'])
            ->raw(') ? (')
            ->compile($this->nodes['expr2'])
            ->raw(') : (')
            ->compile($this->nodes['expr3'])
            ->raw('))')
        ;
    }

    public function evaluate($functions, $values)
    {
        if ($this->nodes['expr1']->evaluate($functions, $values)) {
            return $this->nodes['expr2']->evaluate($functions, $values);
        }

        return $this->nodes['expr3']->evaluate($functions, $values);
    }

    public function toArray()
    {
        return array('(', $this->nodes['expr1'], ' ? ', $this->nodes['expr2'], ' : ', $this->nodes['expr3'], ')');
    }
}

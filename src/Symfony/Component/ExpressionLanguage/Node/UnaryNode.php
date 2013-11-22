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

class UnaryNode extends Node
{
    private static $operators = array(
        '!' => '!',
        'not' => '!',
        '+' => '+',
        '-' => '-',
    );

    public function __construct($operator, Node $node)
    {
        parent::__construct(
            array('node' => $node),
            array('operator' => $operator)
        );
    }

    public function compile(Compiler $compiler)
    {
        $compiler
            ->raw('(')
            ->raw(self::$operators[$this->attributes['operator']])
            ->compile($this->nodes['node'])
            ->raw(')')
        ;
    }

    public function evaluate($functions, $values)
    {
        $value = $this->nodes['node']->evaluate($functions, $values);
        switch ($this->attributes['operator']) {
            case 'not':
            case '!':
                return !$value;
            case '-':
                return -$value;
        }

        return $value;
    }
}

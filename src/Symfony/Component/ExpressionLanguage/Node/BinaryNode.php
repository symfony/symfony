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

class BinaryNode extends Node
{
    private $operators = array(
        '~'     => '.',
        'and'   => '&&',
        'or'    => '||',
    );

    private $functions = array(
        '**'     => 'pow',
        '..'     => 'range',
        'in'     => 'in_array',
        'not in' => '!in_array',
    );

    public function __construct($operator, Node $left, Node $right)
    {
        $this->nodes = array('left' => $left, 'right' => $right);
        $this->attributes = array('operator' => $operator);
    }

    public function compile(Compiler $compiler)
    {
        $operator = $this->attributes['operator'];

        if ('=~' == $operator || '!~' == $operator) {
            $compiler
                ->raw(('!~' == $operator ? '!' : '').'preg_match(')
                ->compile($this->nodes['right'])
                ->raw(', ')
                ->compile($this->nodes['left'])
                ->raw(')')
            ;

            return;
        }

        if (isset($this->functions[$operator])) {
            $compiler
                ->raw(sprintf('%s(', $this->functions[$operator]))
                ->compile($this->nodes['left'])
                ->raw(', ')
                ->compile($this->nodes['right'])
                ->raw(')')
            ;

            return;
        }

        if (isset($this->operators[$operator])) {
            $operator = $this->operators[$operator];
        }

        $compiler
            ->raw('(')
            ->compile($this->nodes['left'])
            ->raw(' ')
            ->raw($operator)
            ->raw(' ')
            ->compile($this->nodes['right'])
            ->raw(')')
        ;
    }

    public function evaluate($functions, $values)
    {
        $operator = $this->attributes['operator'];
        $left = $this->nodes['left']->evaluate($functions, $values);
        $right = $this->nodes['right']->evaluate($functions, $values);

        if (isset($this->functions[$operator])) {
            if ('not in' == $operator) {
                return !call_user_func('in_array', $left, $right);
            }

            return call_user_func($this->functions[$operator], $left, $right);
        }

        switch ($operator) {
            case 'or':
            case '||':
                return $left || $right;
            case 'and':
            case '&&':
                return $left && $right;
            case '|':
                return $left | $right;
            case '^':
                return $left ^ $right;
            case '&':
                return $left & $right;
            case '==':
                return $left == $right;
            case '===':
                return $left === $right;
            case '!=':
                return $left != $right;
            case '!==':
                return $left !== $right;
            case '<':
                return $left < $right;
            case '>':
                return $left > $right;
            case '>=':
                return $left >= $right;
            case '<=':
                return $left <= $right;
            case 'not in':
                return !in_array($left, $right);
            case 'in':
                return in_array($left, $right);
            case '+':
                return $left + $right;
            case '-':
                return $left - $right;
            case '~':
                return $left.$right;
            case '*':
                return $left * $right;
            case '/':
                return $left / $right;
            case '%':
                return $left % $right;
            case '=~':
                return preg_match($right, $left);
            case '!~':
                return !preg_match($right, $left);
        }
    }
}

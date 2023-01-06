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
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class BinaryNode extends Node
{
    private const OPERATORS = [
        '~' => '.',
        'and' => '&&',
        'or' => '||',
    ];

    private const FUNCTIONS = [
        '**' => 'pow',
        '..' => 'range',
        'in' => 'in_array',
        'not in' => '!in_array',
        'contains' => 'str_contains',
        'starts with' => 'str_starts_with',
        'ends with' => 'str_ends_with',
    ];

    public function __construct(string $operator, Node $left, Node $right)
    {
        parent::__construct(
            ['left' => $left, 'right' => $right],
            ['operator' => $operator]
        );
    }

    public function compile(Compiler $compiler)
    {
        $operator = $this->attributes['operator'];

        if ('matches' == $operator) {
            if ($this->nodes['right'] instanceof ConstantNode) {
                $this->evaluateMatches($this->nodes['right']->evaluate([], []), '');
            }

            $compiler
                ->raw('(static function ($regexp, $str) { set_error_handler(function ($t, $m) use ($regexp, $str) { throw new \Symfony\Component\ExpressionLanguage\SyntaxError(sprintf(\'Regexp "%s" passed to "matches" is not valid\', $regexp).substr($m, 12)); }); try { return preg_match($regexp, (string) $str); } finally { restore_error_handler(); } })(')
                ->compile($this->nodes['right'])
                ->raw(', ')
                ->compile($this->nodes['left'])
                ->raw(')')
            ;

            return;
        }

        if (isset(self::FUNCTIONS[$operator])) {
            $compiler
                ->raw(sprintf('%s(', self::FUNCTIONS[$operator]))
                ->compile($this->nodes['left'])
                ->raw(', ')
                ->compile($this->nodes['right'])
                ->raw(')')
            ;

            return;
        }

        if (isset(self::OPERATORS[$operator])) {
            $operator = self::OPERATORS[$operator];
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

    public function evaluate(array $functions, array $values)
    {
        $operator = $this->attributes['operator'];
        $left = $this->nodes['left']->evaluate($functions, $values);

        if (isset(self::FUNCTIONS[$operator])) {
            $right = $this->nodes['right']->evaluate($functions, $values);

            if ('not in' === $operator) {
                return !\in_array($left, $right);
            }
            $f = self::FUNCTIONS[$operator];

            return $f($left, $right);
        }

        switch ($operator) {
            case 'or':
            case '||':
                return $left || $this->nodes['right']->evaluate($functions, $values);
            case 'and':
            case '&&':
                return $left && $this->nodes['right']->evaluate($functions, $values);
        }

        $right = $this->nodes['right']->evaluate($functions, $values);

        switch ($operator) {
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
                return !\in_array($left, $right);
            case 'in':
                return \in_array($left, $right);
            case '+':
                return $left + $right;
            case '-':
                return $left - $right;
            case '~':
                return $left.$right;
            case '*':
                return $left * $right;
            case '/':
                if (0 == $right) {
                    throw new \DivisionByZeroError('Division by zero.');
                }

                return $left / $right;
            case '%':
                if (0 == $right) {
                    throw new \DivisionByZeroError('Modulo by zero.');
                }

                return $left % $right;
            case 'matches':
                return $this->evaluateMatches($right, $left);
        }
    }

    public function toArray()
    {
        return ['(', $this->nodes['left'], ' '.$this->attributes['operator'].' ', $this->nodes['right'], ')'];
    }

    private function evaluateMatches(string $regexp, ?string $str): int
    {
        set_error_handler(function ($t, $m) use ($regexp) {
            throw new SyntaxError(sprintf('Regexp "%s" passed to "matches" is not valid', $regexp).substr($m, 12));
        });
        try {
            return preg_match($regexp, (string) $str);
        } finally {
            restore_error_handler();
        }
    }
}

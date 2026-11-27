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
class ArrayNode extends Node
{
    protected int $index;

    public function __construct()
    {
        $this->index = -1;
    }

    public function addElement(Node $value, ?Node $key = null): void
    {
        $key ??= new ConstantNode(++$this->index);

        array_push($this->nodes, $key, $value);
    }

    /**
     * Compiles the node to PHP.
     */
    public function compile(Compiler $compiler): void
    {
        $compiler->raw('[');
        $this->compileArguments($compiler);
        $compiler->raw(']');
    }

    public function evaluate(array $functions, array $values): array
    {
        $result = [];
        foreach ($this->getKeyValuePairs() as $pair) {
            $result[$pair['key']->evaluate($functions, $values)] = $pair['value']->evaluate($functions, $values);
        }

        return $result;
    }

    public function toArray(): array
    {
        $pairs = $this->getKeyValuePairs();
        $isList = true;
        foreach ($pairs as $i => $pair) {
            if (!$pair['key'] instanceof ConstantNode || $i !== $pair['key']->attributes['value']) {
                $isList = false;
                break;
            }
        }

        $array = [];
        foreach ($pairs as $pair) {
            $array[] = ', ';
            if (!$isList) {
                $key = $pair['key'];
                // Non-literal hash keys must be enclosed in parentheses.
                if (!$key instanceof ConstantNode || !(\is_string($key->attributes['value']) || ((\is_int($key->attributes['value']) || \is_float($key->attributes['value'])) && $key->attributes['value'] >= 0))) {
                    $array[] = '(';
                    $array[] = $key;
                    $array[] = ')';
                } else {
                    $array[] = $key;
                }
                $array[] = ': ';
            }
            $array[] = $pair['value'];
        }
        $array[0] = $isList ? '[' : '{';
        $array[] = $isList ? ']' : '}';

        return $array;
    }

    protected function getKeyValuePairs(): array
    {
        $pairs = [];
        foreach (array_chunk($this->nodes, 2) as $pair) {
            $pairs[] = ['key' => $pair[0], 'value' => $pair[1]];
        }

        return $pairs;
    }

    protected function compileArguments(Compiler $compiler, bool $withKeys = true): void
    {
        $first = true;
        foreach ($this->getKeyValuePairs() as $pair) {
            if (!$first) {
                $compiler->raw(', ');
            }
            $first = false;

            if ($withKeys) {
                $compiler
                    ->compile($pair['key'])
                    ->raw(' => ')
                ;
            }

            $compiler->compile($pair['value']);
        }
    }
}

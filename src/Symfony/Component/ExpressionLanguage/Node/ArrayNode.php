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
    protected $index;

    public function __construct()
    {
        $this->index = -1;
    }

    public function addElement(Node $value, Node $key = null)
    {
        if (null === $key) {
            $key = new ConstantNode(++$this->index);
        }

        array_push($this->nodes, $key, $value);
    }

    /**
     * Compiles the node to PHP.
     */
    public function compile(Compiler $compiler)
    {
        $compiler->raw('array(');
        $this->compileArguments($compiler);
        $compiler->raw(')');
    }

    public function evaluate($functions, $values)
    {
        $result = array();
        foreach ($this->getKeyValuePairs() as $pair) {
            $result[$pair['key']->evaluate($functions, $values)] = $pair['value']->evaluate($functions, $values);
        }

        return $result;
    }

    protected function getKeyValuePairs()
    {
        $pairs = array();
        foreach (array_chunk($this->nodes, 2) as $pair) {
            $pairs[] = array('key' => $pair[0], 'value' => $pair[1]);
        }

        return $pairs;
    }

    protected function compileArguments(Compiler $compiler, $withKeys = true)
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

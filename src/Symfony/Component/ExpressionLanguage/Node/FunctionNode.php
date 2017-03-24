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
class FunctionNode extends Node
{
    protected $names;

    public function __construct($name, Node $arguments, $names)
    {
        $this->names = $names;
        parent::__construct(
            array('arguments' => $arguments),
            array('name' => $name)
        );
    }

    public function compile(Compiler $compiler)
    {
        $arguments = array();
        foreach ($this->nodes['arguments']->nodes as $node) {
            $arguments[] = $compiler->subcompile($node);
        }
        $name = $this->attributes['name'];
        $function = $compiler->getFunction($name);
        if (is_null($function)) {
            $function = $compiler->getFunction('*');
            $compiler->raw(call_user_func_array($function['compiler'], array($name, $this->names, $arguments)));
        }
        $compiler->raw(call_user_func_array($function['compiler'], $arguments));
    }

    public function evaluate($functions, $values)
    {
        $arguments = array();
        foreach ($this->nodes['arguments']->nodes as $node) {
            $arguments[] = $node->evaluate($functions, $values);
        }
        $name = $this->attributes['name'];
        if (array_key_exists($name, $functions)) {
            return call_user_func_array($functions[$name]['evaluator'], array_merge(array($values), $arguments));
        }
        return call_user_func_array($functions['*']['evaluator'], array($name, $values, $arguments));
    }

    public function toArray()
    {
        $array = array();
        $array[] = $this->attributes['name'];

        foreach ($this->nodes['arguments']->nodes as $node) {
            $array[] = ', ';
            $array[] = $node;
        }
        $array[1] = '(';
        $array[] = ')';

        return $array;
    }
}

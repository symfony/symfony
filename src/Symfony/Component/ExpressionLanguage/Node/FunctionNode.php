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
use Symfony\Component\ExpressionLanguage\RuntimeError;

class FunctionNode extends Node
{
    public function __construct($name, Node $arguments)
    {
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

        $function = $compiler->getFunction($this->attributes['name']);

        $compiler->raw(call_user_func_array($function['compiler'], $arguments));
    }

    public function evaluate($functions, $values)
    {
        $arguments = array($values);
        foreach ($this->nodes['arguments']->nodes as $node) {
            $arguments[] = $node->evaluate($functions, $values);
        }

        $functionName = $this->attributes['name'];

        if (!isset($functions[$functionName])) {
            throw new RuntimeError(sprintf('The function "%s" does not exist', $functionName));
        }

        return call_user_func_array($functions[$functionName]['evaluator'], $arguments);
    }
}

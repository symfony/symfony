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

class ClosureNode extends FunctionNode
{
    public function compile(Compiler $compiler)
    {
        $arguments = array();
        foreach ($this->nodes['arguments']->nodes as $node) {
            $arguments[] = $compiler->subcompile($node);
        }

        $compiler->raw(sprintf('$%s(%s)', $this->attributes['name'], implode(', ', $arguments)));
    }

    public function evaluate($functions, $values)
    {
        $arguments = array();
        foreach ($this->nodes['arguments']->nodes as $node) {
            $arguments[] = $node->evaluate($functions, $values);
        }

        return call_user_func_array($values[$this->attributes['name']], $arguments);
    }
}

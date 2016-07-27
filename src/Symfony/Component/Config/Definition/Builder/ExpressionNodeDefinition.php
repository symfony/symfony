<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\ExpressionNode;

/**
 * This class provides a fluent interface for defining a node.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class ExpressionNodeDefinition extends VariableNodeDefinition
{
    /**
     * Instantiate a Node.
     *
     * @return VariableNode The node
     */
    protected function instantiateNode()
    {
        return new ExpressionNode($this->name, $this->parent);
    }
}

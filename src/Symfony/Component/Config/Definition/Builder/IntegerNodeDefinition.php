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

use Symfony\Component\Config\Definition\IntegerNode;

/**
 * This class provides a fluent interface for defining an integer node.
 *
 * @template TParent of NodeParentInterface|null = null
 *
 * @extends NumericNodeDefinition<TParent>
 *
 * @author Jeanmonod David <david.jeanmonod@gmail.com>
 */
class IntegerNodeDefinition extends NumericNodeDefinition
{
    protected function instantiateNode(): IntegerNode
    {
        return new IntegerNode($this->name, $this->parent, $this->min, $this->max, $this->pathSeparator);
    }
}

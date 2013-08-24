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

use Symfony\Component\Config\Definition\BooleanNode;

/**
 * This class provides a fluent interface for defining a node.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.0.0
 */
class BooleanNodeDefinition extends ScalarNodeDefinition
{
    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function __construct($name, NodeParentInterface $parent = null)
    {
        parent::__construct($name, $parent);

        $this->nullEquivalent = true;
    }

    /**
     * Instantiate a Node
     *
     * @return BooleanNode The node
     *
     * @since v2.0.0
     */
    protected function instantiateNode()
    {
        return new BooleanNode($this->name, $this->parent);
    }

}

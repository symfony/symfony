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
 */
class BooleanNodeDefinition extends ScalarNodeDefinition
{
    /**
     * {@inheritdoc}
     */
    public function __construct($name, NodeParentInterface $parent = null)
    {
        parent::__construct($name, $parent);

        $this->nullEquivalent = true;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Deprecated since version 2.8, to be removed in 3.0.
     */
    public function cannotBeEmpty()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

        return parent::cannotBeEmpty();
    }

    /**
     * Instantiate a Node.
     *
     * @return BooleanNode The node
     */
    protected function instantiateNode()
    {
        return new BooleanNode($this->name, $this->parent);
    }
}

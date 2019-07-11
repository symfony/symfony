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
    public function __construct(?string $name, NodeParentInterface $parent = null)
    {
        parent::__construct($name, $parent);

        $this->nullEquivalent = true;
        $this->allowEmptyValue = false;
    }

    /**
     * Instantiate a Node.
     *
     * @return BooleanNode The node
     */
    protected function instantiateNode()
    {
        return new BooleanNode($this->name, $this->parent, $this->pathSeparator);
    }

    public function cannotBeEmpty()
    {
        $this->nullEquivalent = true;

        return parent::cannotBeEmpty();
    }

    public function allowNull(): self
    {
        $this->allowEmptyValue = true;
        $this->nullEquivalent = null;

        return $this;
    }
}

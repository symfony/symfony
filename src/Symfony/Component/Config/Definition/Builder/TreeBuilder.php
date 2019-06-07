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

use Symfony\Component\Config\Definition\Exception\TreeWithoutRootNodeException;
use Symfony\Component\Config\Definition\NodeInterface;

/**
 * This is the entry class for building a config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class TreeBuilder implements NodeParentInterface
{
    protected $tree;
    protected $root;

    public function __construct(string $name, string $type = 'array', NodeBuilder $builder = null)
    {
        $builder = $builder ?: new NodeBuilder();
        $this->root = $builder->node($name, $type)->setParent($this);
    }

    /**
     * @return NodeDefinition|ArrayNodeDefinition The root node (as an ArrayNodeDefinition when the type is 'array')
     */
    public function getRootNode(): NodeDefinition
    {
        if (null === $this->root) {
            throw new \RuntimeException(sprintf('Calling %s() before creating the root node is not supported, migrate to the new constructor signature instead.', __METHOD__));
        }

        return $this->root;
    }

    /**
     * Builds the tree.
     *
     * @return NodeInterface
     *
     * @throws \RuntimeException
     */
    public function buildTree()
    {
        $this->assertTreeHasRootNode();
        if (null !== $this->tree) {
            return $this->tree;
        }

        return $this->tree = $this->root->getNode(true);
    }

    public function setPathSeparator(string $separator)
    {
        $this->assertTreeHasRootNode();

        // unset last built as changing path separator changes all nodes
        $this->tree = null;

        $this->root->setPathSeparator($separator);
    }

    /**
     * @throws \RuntimeException if root node is not defined
     */
    private function assertTreeHasRootNode()
    {
        if (null === $this->root) {
            throw new TreeWithoutRootNodeException('The configuration tree has no root node.');
        }
    }
}

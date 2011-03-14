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

/**
 * This class provides a fluent interface for building a node.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class NodeBuilder implements NodeParentInterface
{
    protected $parent;

    /**
     * Set the parent node
     *
     * @param ParentNodeDefinitionInterface $parent The parent node
     *
     * @return NodeBuilder This node builder
     */
    public function setParent(ParentNodeDefinitionInterface $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Creates a child array node
     *
     * @param string $name The name of the node
     *
     * @return ArrayNodeDefinition The child node
     */
    public function arrayNode($name)
    {
        return $this->node($name, 'array');
    }

    /**
     * Creates a child scalar node.
     *
     * @param string $name the name of the node
     *
     * @return ScalarNodeDefinition The child node
     */
    public function scalarNode($name)
    {
        return $this->node($name, 'scalar');
    }

    /**
     * Creates a child Boolean node.
     *
     * @param string $name The name of the node
     *
     * @return BooleanNodeDefinition The child node
     */
    public function booleanNode($name)
    {
        return $this->node($name, 'boolean');
    }

    /**
     * Creates a child variable node.
     *
     * @param string $name The name of the node
     *
     * @return VariableNodeDefinition The builder of the child node
     */
    public function variableNode($name)
    {
        return $this->node($name, 'variable');
    }
    
    /**
     * Returns the parent node.
     *
     * @return ParentNodeDefinitionInterface The parent node
     */
    public function end()
    {
        return $this->parent;
    }

    /**
     * Creates a child node.
     *
     * @param string $name The name of the node
     * @param string $type The type of the node
     *
     * @return NodeDefinition The child node
     *
     * @throws \RuntimeException When the node type is not supported
     */
    public function node($name, $type)
    {
        $class = $this->getNodeClass($type);

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf('Unknown node type: "%s"', $type));
        }

        $node = new $class($name);

        if ($node instanceof ParentNodeDefinitionInterface) {
            $builder = clone $this;
            $builder->setParent(null);
            $node->setBuilder($builder);
        }

        if (null !== $this->parent) {
            $this->parent->append($node);
            // Make this builder the node parent to allow for a fluid interface
            $node->setParent($this);
        }

        return $node;
    }

    /**
     * Returns the class name of the node definition
     * 
     * @param string $type The node type
     * @return string The node definition class name
     */
    protected function getNodeClass($type)
    {
        return $class = __NAMESPACE__.'\\'.ucfirst($type).'NodeDefinition';
    }

}
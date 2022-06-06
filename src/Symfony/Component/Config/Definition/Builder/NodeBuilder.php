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
    protected $nodeMapping;

    public function __construct()
    {
        $this->nodeMapping = [
            'variable' => VariableNodeDefinition::class,
            'scalar' => ScalarNodeDefinition::class,
            'boolean' => BooleanNodeDefinition::class,
            'integer' => IntegerNodeDefinition::class,
            'float' => FloatNodeDefinition::class,
            'array' => ArrayNodeDefinition::class,
            'enum' => EnumNodeDefinition::class,
        ];
    }

    /**
     * Set the parent node.
     *
     * @return $this
     */
    public function setParent(ParentNodeDefinitionInterface $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Creates a child array node.
     *
     * @return ArrayNodeDefinition
     */
    public function arrayNode(string $name)
    {
        return $this->node($name, 'array');
    }

    /**
     * Creates a child scalar node.
     *
     * @return ScalarNodeDefinition
     */
    public function scalarNode(string $name)
    {
        return $this->node($name, 'scalar');
    }

    /**
     * Creates a child Boolean node.
     *
     * @return BooleanNodeDefinition
     */
    public function booleanNode(string $name)
    {
        return $this->node($name, 'boolean');
    }

    /**
     * Creates a child integer node.
     *
     * @return IntegerNodeDefinition
     */
    public function integerNode(string $name)
    {
        return $this->node($name, 'integer');
    }

    /**
     * Creates a child float node.
     *
     * @return FloatNodeDefinition
     */
    public function floatNode(string $name)
    {
        return $this->node($name, 'float');
    }

    /**
     * Creates a child EnumNode.
     *
     * @return EnumNodeDefinition
     */
    public function enumNode(string $name)
    {
        return $this->node($name, 'enum');
    }

    /**
     * Creates a child variable node.
     *
     * @return VariableNodeDefinition
     */
    public function variableNode(string $name)
    {
        return $this->node($name, 'variable');
    }

    /**
     * Returns the parent node.
     *
     * @return NodeDefinition&ParentNodeDefinitionInterface
     */
    public function end()
    {
        return $this->parent;
    }

    /**
     * Creates a child node.
     *
     * @return NodeDefinition
     *
     * @throws \RuntimeException When the node type is not registered
     * @throws \RuntimeException When the node class is not found
     */
    public function node(?string $name, string $type)
    {
        $class = $this->getNodeClass($type);

        $node = new $class($name);

        $this->append($node);

        return $node;
    }

    /**
     * Appends a node definition.
     *
     * Usage:
     *
     *     $node = new ArrayNodeDefinition('name')
     *         ->children()
     *             ->scalarNode('foo')->end()
     *             ->scalarNode('baz')->end()
     *             ->append($this->getBarNodeDefinition())
     *         ->end()
     *     ;
     *
     * @return $this
     */
    public function append(NodeDefinition $node)
    {
        if ($node instanceof BuilderAwareInterface) {
            $builder = clone $this;
            $builder->setParent(null);
            $node->setBuilder($builder);
        }

        if (null !== $this->parent) {
            $this->parent->append($node);
            // Make this builder the node parent to allow for a fluid interface
            $node->setParent($this);
        }

        return $this;
    }

    /**
     * Adds or overrides a node Type.
     *
     * @param string $type  The name of the type
     * @param string $class The fully qualified name the node definition class
     *
     * @return $this
     */
    public function setNodeClass(string $type, string $class)
    {
        $this->nodeMapping[strtolower($type)] = $class;

        return $this;
    }

    /**
     * Returns the class name of the node definition.
     *
     * @return string
     *
     * @throws \RuntimeException When the node type is not registered
     * @throws \RuntimeException When the node class is not found
     */
    protected function getNodeClass(string $type)
    {
        $type = strtolower($type);

        if (!isset($this->nodeMapping[$type])) {
            throw new \RuntimeException(sprintf('The node type "%s" is not registered.', $type));
        }

        $class = $this->nodeMapping[$type];

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf('The node class "%s" does not exist.', $class));
        }

        return $class;
    }
}

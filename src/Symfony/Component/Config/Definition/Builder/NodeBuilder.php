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
 * @template TParent of (NodeDefinition&ParentNodeDefinitionInterface)|null
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class NodeBuilder implements NodeParentInterface
{
    /**
     * @var TParent
     */
    protected (NodeDefinition&ParentNodeDefinitionInterface)|null $parent = null;
    /**
     * @var array<string, class-string<NodeDefinition>>
     */
    protected array $nodeMapping;

    public function __construct()
    {
        // This list should be in sync with generics on method node() below and on TreeBuilder, ArrayNodeDefinition and DefinitionConfigurator
        $this->nodeMapping = [
            'array' => ArrayNodeDefinition::class,
            'variable' => VariableNodeDefinition::class,
            'scalar' => ScalarNodeDefinition::class,
            'string' => StringNodeDefinition::class,
            'boolean' => BooleanNodeDefinition::class,
            'integer' => IntegerNodeDefinition::class,
            'float' => FloatNodeDefinition::class,
            'enum' => EnumNodeDefinition::class,
        ];
    }

    /**
     * Set the parent node.
     *
     * @template TNewParent of (NodeDefinition&ParentNodeDefinitionInterface)|null
     *
     * @psalm-this-out static<TNewParent>
     *
     * @return $this
     */
    public function setParent((NodeDefinition&ParentNodeDefinitionInterface)|null $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Creates a child array node.
     *
     * @param string|null $singular The singular name of the node when $name is plural
     *
     * @return ArrayNodeDefinition<$this>
     */
    public function arrayNode(string $name/* , ?string $singular = null */): ArrayNodeDefinition
    {
        $singular = 1 < \func_num_args() ? func_get_arg(1) : null;
        if (null !== $singular) {
            if (!$this->parent instanceof ArrayNodeDefinition) {
                throw new \LogicException('The parent node must be an ArrayNodeDefinition when setting the singular name.');
            }
            $this->parent->fixXmlConfig($singular, $name);
        }

        return $this->node($name, 'array');
    }

    /**
     * Creates a child scalar node.
     *
     * @return ScalarNodeDefinition<$this>
     */
    public function scalarNode(string $name): ScalarNodeDefinition
    {
        return $this->node($name, 'scalar');
    }

    /**
     * Creates a child Boolean node.
     *
     * @return BooleanNodeDefinition<$this>
     */
    public function booleanNode(string $name): BooleanNodeDefinition
    {
        return $this->node($name, 'boolean');
    }

    /**
     * Creates a child integer node.
     *
     * @return IntegerNodeDefinition<$this>
     */
    public function integerNode(string $name): IntegerNodeDefinition
    {
        return $this->node($name, 'integer');
    }

    /**
     * Creates a child float node.
     *
     * @return FloatNodeDefinition<$this>
     */
    public function floatNode(string $name): FloatNodeDefinition
    {
        return $this->node($name, 'float');
    }

    /**
     * Creates a child EnumNode.
     *
     * @return EnumNodeDefinition<$this>
     */
    public function enumNode(string $name): EnumNodeDefinition
    {
        return $this->node($name, 'enum');
    }

    /**
     * Creates a child variable node.
     *
     * @return VariableNodeDefinition<$this>
     */
    public function variableNode(string $name): VariableNodeDefinition
    {
        return $this->node($name, 'variable');
    }

    /**
     * Creates a child string node.
     *
     * @return StringNodeDefinition<$this>
     */
    public function stringNode(string $name): StringNodeDefinition
    {
        return $this->node($name, 'string');
    }

    /**
     * Returns the parent node.
     *
     * @return TParent
     */
    public function end(): (NodeDefinition&ParentNodeDefinitionInterface)|null
    {
        return $this->parent;
    }

    /**
     * Creates a child node.
     *
     * @template T of 'array'|'variable'|'scalar'|'string'|'boolean'|'integer'|'float'|'enum'
     *
     * @return (
     *    T is 'array' ? ArrayNodeDefinition<$this>
     *    : (T is 'variable' ? VariableNodeDefinition<$this>
     *    : (T is 'scalar' ? ScalarNodeDefinition<$this>
     *    : (T is 'string' ? StringNodeDefinition<$this>
     *    : (T is 'boolean' ? BooleanNodeDefinition<$this>
     *    : (T is 'integer' ? IntegerNodeDefinition<$this>
     *    : (T is 'float' ? FloatNodeDefinition<$this>
     *    : (T is 'enum' ? EnumNodeDefinition<$this>
     *    : NodeDefinition<$this>)))))))
     * )
     *
     * @throws \RuntimeException When the node type is not registered
     * @throws \RuntimeException When the node class is not found
     */
    public function node(?string $name, string $type): NodeDefinition
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
    public function append(NodeDefinition $node): static
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
     * @param string                       $type  The name of the type
     * @param class-string<NodeDefinition> $class The fully qualified name the node definition class
     *
     * @return $this
     */
    public function setNodeClass(string $type, string $class): static
    {
        $this->nodeMapping[strtolower($type)] = $class;

        return $this;
    }

    /**
     * Returns the class name of the node definition.
     *
     * @throws \RuntimeException When the node type is not registered
     * @throws \RuntimeException When the node class is not found
     */
    protected function getNodeClass(string $type): string
    {
        $type = strtolower($type);

        if (!isset($this->nodeMapping[$type])) {
            throw new \RuntimeException(\sprintf('The node type "%s" is not registered.', $type));
        }

        $class = $this->nodeMapping[$type];

        if (!class_exists($class)) {
            throw new \RuntimeException(\sprintf('The node class "%s" does not exist.', $class));
        }

        return $class;
    }
}

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

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Exception\InvalidDefinitionException;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;

/**
 * This is the entry class for building a config tree.
 *
 * @template T of 'array'|'variable'|'scalar'|'string'|'boolean'|'integer'|'float'|'enum' = 'array'
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class TreeBuilder implements NodeParentInterface
{
    protected ?NodeInterface $tree = null;
    /**
     * @var NodeDefinition<$this>|null
     */
    protected ?NodeDefinition $root = null;

    /**
     * @param T $type
     */
    public function __construct(string $name, string $type = 'array', ?NodeBuilder $builder = null)
    {
        $builder ??= new NodeBuilder();
        $this->root = $builder->node($name, $type)->setParent($this);
    }

    /**
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
     */
    public function getRootNode(): NodeDefinition
    {
        return $this->root;
    }

    public function buildTree(): NodeInterface
    {
        if (null !== $this->tree) {
            return $this->tree;
        }

        self::checkAliases($tree = $this->root->getNode(true), 0, $tree->getPath());

        return $this->tree = $tree;
    }

    public function setPathSeparator(string $separator): void
    {
        // unset last built as changing path separator changes all nodes
        $this->tree = null;

        $this->root->setPathSeparator($separator);
    }

    private static function checkAliases(NodeInterface $node, int $depth, string $path): void
    {
        if ($node instanceof BaseNode && null !== $node->getAttribute('alias_of')) {
            if (1 !== $depth) {
                throw new InvalidDefinitionException(\sprintf('Only the direct children of a root node can declare an "alias_of" attribute, but "%s" does.', $path));
            }

            if ($node instanceof ScalarNode) {
                throw new InvalidDefinitionException(\sprintf('The value of a node declaring an "alias_of" attribute is forwarded as the configuration of the aliased extension, so the node must accept arrays, but "%s" does not.', $path));
            }
        }

        if ($node instanceof PrototypedArrayNode) {
            self::checkAliases($node->getPrototype(), 1 + $depth, $path.'[]');
        } elseif ($node instanceof ArrayNode) {
            foreach ($node->getChildren() as $child) {
                self::checkAliases($child, 1 + $depth, $child->getPath());
            }
        }
    }
}

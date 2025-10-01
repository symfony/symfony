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

use Symfony\Component\Config\Definition\NodeInterface;

/**
 * This is the entry class for building a config tree.
 *
 * @template T of 'array'|'variable'|'scalar'|'string'|'boolean'|'integer'|'float'|'enum'
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
        return $this->tree ??= $this->root->getNode(true);
    }

    public function setPathSeparator(string $separator): void
    {
        // unset last built as changing path separator changes all nodes
        $this->tree = null;

        $this->root->setPathSeparator($separator);
    }
}

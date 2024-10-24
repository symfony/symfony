<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\DataModel\Decode;

use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\UnionType;

/**
 * Represents a "OR" node composition in the data model graph representation.
 *
 * Composing nodes are sorted by their precision (descending).
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class CompositeNode implements DataModelNodeInterface
{
    private const NODE_PRECISION = [
        CollectionNode::class => 3,
        ObjectNode::class => 2,
        BackedEnumNode::class => 1,
        ScalarNode::class => 0,
    ];

    /**
     * @var list<DataModelNodeInterface>
     */
    private array $nodes;

    /**
     * @param list<DataModelNodeInterface> $nodes
     */
    public function __construct(array $nodes)
    {
        if (\count($nodes) < 2) {
            throw new InvalidArgumentException(\sprintf('"%s" expects at least 2 nodes.', self::class));
        }

        foreach ($nodes as $n) {
            if ($n instanceof self) {
                throw new InvalidArgumentException(\sprintf('Cannot set "%s" as a "%s" node.', self::class, self::class));
            }
        }

        usort($nodes, fn (CollectionNode|ObjectNode|BackedEnumNode|ScalarNode $a, CollectionNode|ObjectNode|BackedEnumNode|ScalarNode $b): int => self::NODE_PRECISION[$b::class] <=> self::NODE_PRECISION[$a::class]);
        $this->nodes = $nodes;
    }

    public function getIdentifier(): string
    {
        return (string) $this->getType();
    }

    public function getType(): UnionType
    {
        return Type::union(...array_map(fn (DataModelNodeInterface $n): Type => $n->getType(), $this->nodes));
    }

    /**
     * @return list<DataModelNodeInterface>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}

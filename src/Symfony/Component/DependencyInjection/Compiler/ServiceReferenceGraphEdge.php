<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

/**
 * Represents an edge in your service graph.
 *
 * Value is typically a reference.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ServiceReferenceGraphEdge
{
    public function __construct(
        private ServiceReferenceGraphNode $sourceNode,
        private ServiceReferenceGraphNode $destNode,
        private mixed $value = null,
        private bool $lazy = false,
        private bool $weak = false,
        private bool $byConstructor = false,
    ) {
    }

    /**
     * Returns the value of the edge.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Returns the source node.
     */
    public function getSourceNode(): ServiceReferenceGraphNode
    {
        return $this->sourceNode;
    }

    /**
     * Returns the destination node.
     */
    public function getDestNode(): ServiceReferenceGraphNode
    {
        return $this->destNode;
    }

    /**
     * Returns true if the edge is lazy, meaning it's a dependency not requiring direct instantiation.
     */
    public function isLazy(): bool
    {
        return $this->lazy;
    }

    /**
     * Returns true if the edge is weak, meaning it shouldn't prevent removing the target service.
     */
    public function isWeak(): bool
    {
        return $this->weak;
    }

    /**
     * Returns true if the edge links with a constructor argument.
     */
    public function isReferencedByConstructor(): bool
    {
        return $this->byConstructor;
    }
}

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
    private $sourceNode;
    private $destNode;
    private $value;
    private $lazy;
    private $weak;
    private $byConstructor;

    /**
     * @param mixed $value
     * @param bool  $lazy
     * @param bool  $weak
     * @param bool  $byConstructor
     */
    public function __construct(ServiceReferenceGraphNode $sourceNode, ServiceReferenceGraphNode $destNode, $value = null, $lazy = false, $weak = false, $byConstructor = false)
    {
        $this->sourceNode = $sourceNode;
        $this->destNode = $destNode;
        $this->value = $value;
        $this->lazy = $lazy;
        $this->weak = $weak;
        $this->byConstructor = $byConstructor;
    }

    /**
     * Returns the value of the edge.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the source node.
     *
     * @return ServiceReferenceGraphNode
     */
    public function getSourceNode()
    {
        return $this->sourceNode;
    }

    /**
     * Returns the destination node.
     *
     * @return ServiceReferenceGraphNode
     */
    public function getDestNode()
    {
        return $this->destNode;
    }

    /**
     * Returns true if the edge is lazy, meaning it's a dependency not requiring direct instantiation.
     *
     * @return bool
     */
    public function isLazy()
    {
        return $this->lazy;
    }

    /**
     * Returns true if the edge is weak, meaning it shouldn't prevent removing the target service.
     *
     * @return bool
     */
    public function isWeak()
    {
        return $this->weak;
    }

    /**
     * Returns true if the edge links with a constructor argument.
     *
     * @return bool
     */
    public function isReferencedByConstructor()
    {
        return $this->byConstructor;
    }
}

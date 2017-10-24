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

    /**
     * @param ServiceReferenceGraphNode $sourceNode
     * @param ServiceReferenceGraphNode $destNode
     * @param mixed                     $value
     * @param bool                      $lazy
     */
    public function __construct(ServiceReferenceGraphNode $sourceNode, ServiceReferenceGraphNode $destNode, $value = null, $lazy = false)
    {
        $this->sourceNode = $sourceNode;
        $this->destNode = $destNode;
        $this->value = $value;
        $this->lazy = $lazy;
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
}

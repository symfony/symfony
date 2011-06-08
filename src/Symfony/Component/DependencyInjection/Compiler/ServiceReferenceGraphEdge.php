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

    /**
     * Constructor.
     *
     * @param ServiceReferenceGraphNode $sourceNode
     * @param ServiceReferenceGraphNode $destNode
     * @param string $value
     */
    public function __construct(ServiceReferenceGraphNode $sourceNode, ServiceReferenceGraphNode $destNode, $value = null)
    {
        $this->sourceNode = $sourceNode;
        $this->destNode = $destNode;
        $this->value = $value;
    }

    /**
     * Returns the value of the edge
     *
     * @return ServiceReferenceGraphNode
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the source node
     *
     * @return ServiceReferenceGraphNode
     */
    public function getSourceNode()
    {
        return $this->sourceNode;
    }

    /**
     * Returns the destination node
     *
     * @return ServiceReferenceGraphNode
     */
    public function getDestNode()
    {
        return $this->destNode;
    }
}

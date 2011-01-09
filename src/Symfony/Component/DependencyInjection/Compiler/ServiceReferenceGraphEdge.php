<?php

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
    protected $sourceNode;
    protected $destNode;
    protected $value;

    public function __construct(ServiceReferenceGraphNode $sourceNode, ServiceReferenceGraphNode $destNode, $value = null)
    {
        $this->sourceNode = $sourceNode;
        $this->destNode = $destNode;
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getSourceNode()
    {
        return $this->sourceNode;
    }

    public function getDestNode()
    {
        return $this->destNode;
    }
}
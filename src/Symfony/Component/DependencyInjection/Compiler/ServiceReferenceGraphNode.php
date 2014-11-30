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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Alias;

/**
 * Represents a node in your service graph.
 *
 * Value is typically a definition, or an alias.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ServiceReferenceGraphNode
{
    private $id;
    private $inEdges;
    private $outEdges;
    private $value;

    /**
     * Constructor.
     *
     * @param string $id    The node identifier
     * @param mixed  $value The node value
     */
    public function __construct($id, $value)
    {
        $this->id = $id;
        $this->value = $value;
        $this->inEdges = array();
        $this->outEdges = array();
    }

    /**
     * Adds an in edge to this node.
     *
     * @param ServiceReferenceGraphEdge $edge
     */
    public function addInEdge(ServiceReferenceGraphEdge $edge)
    {
        $this->inEdges[] = $edge;
    }

    /**
     * Adds an out edge to this node.
     *
     * @param ServiceReferenceGraphEdge $edge
     */
    public function addOutEdge(ServiceReferenceGraphEdge $edge)
    {
        $this->outEdges[] = $edge;
    }

    /**
     * Checks if the value of this node is an Alias.
     *
     * @return bool True if the value is an Alias instance
     */
    public function isAlias()
    {
        return $this->value instanceof Alias;
    }

    /**
     * Checks if the value of this node is a Definition.
     *
     * @return bool True if the value is a Definition instance
     */
    public function isDefinition()
    {
        return $this->value instanceof Definition;
    }

    /**
     * Returns the identifier.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the in edges.
     *
     * @return array The in ServiceReferenceGraphEdge array
     */
    public function getInEdges()
    {
        return $this->inEdges;
    }

    /**
     * Returns the out edges.
     *
     * @return array The out ServiceReferenceGraphEdge array
     */
    public function getOutEdges()
    {
        return $this->outEdges;
    }

    /**
     * Returns the value of this Node
     *
     * @return mixed The value
     */
    public function getValue()
    {
        return $this->value;
    }
}

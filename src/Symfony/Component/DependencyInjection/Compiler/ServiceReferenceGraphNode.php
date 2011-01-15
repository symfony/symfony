<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
    protected $id;
    protected $inEdges;
    protected $outEdges;
    protected $value;

    public function __construct($id, $value)
    {
        $this->id = $id;
        $this->value = $value;
        $this->inEdges = array();
        $this->outEdges = array();
    }

    public function addInEdge(ServiceReferenceGraphEdge $edge)
    {
        $this->inEdges[] = $edge;
    }

    public function addOutEdge(ServiceReferenceGraphEdge $edge)
    {
        $this->outEdges[] = $edge;
    }

    public function isAlias()
    {
        return $this->value instanceof Alias;
    }

    public function isDefinition()
    {
        return $this->value instanceof Definition;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getInEdges()
    {
        return $this->inEdges;
    }

    public function getOutEdges()
    {
        return $this->outEdges;
    }

    public function getValue()
    {
        return $this->value;
    }
}
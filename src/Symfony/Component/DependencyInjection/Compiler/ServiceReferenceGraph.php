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

/**
 * This is a directed graph of your services.
 *
 * This information can be used by your compiler passes instead of collecting
 * it themselves which improves performance quite a lot.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ServiceReferenceGraph
{
    protected $nodes;

    public function __construct()
    {
        $this->nodes = array();
    }

    public function hasNode($id)
    {
        return isset($this->nodes[$id]);
    }

    public function getNode($id)
    {
        if (!isset($this->nodes[$id])) {
            throw new \InvalidArgumentException(sprintf('There is no node with id "%s".', $id));
        }

        return $this->nodes[$id];
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function clear()
    {
        $this->nodes = array();
    }

    public function connect($sourceId, $sourceValue, $destId, $destValue = null, $reference = null)
    {
        $sourceNode = $this->createNode($sourceId, $sourceValue);
        $destNode = $this->createNode($destId, $destValue);
        $edge = new ServiceReferenceGraphEdge($sourceNode, $destNode, $reference);

        $sourceNode->addOutEdge($edge);
        $destNode->addInEdge($edge);
    }

    protected function createNode($id, $value)
    {
        if (isset($this->nodes[$id]) && $this->nodes[$id]->getValue() === $value) {
            return $this->nodes[$id];
        }

        return $this->nodes[$id] = new ServiceReferenceGraphNode($id, $value);
    }
}
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

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

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
    /**
     * @var ServiceReferenceGraphNode[]
     */
    private $nodes = array();

    /**
     * Checks if the graph has a specific node.
     *
     * @param string $id Id to check
     *
     * @return bool
     */
    public function hasNode($id)
    {
        return isset($this->nodes[$id]);
    }

    /**
     * Gets a node by identifier.
     *
     * @param string $id The id to retrieve
     *
     * @return ServiceReferenceGraphNode
     *
     * @throws InvalidArgumentException if no node matches the supplied identifier
     */
    public function getNode($id)
    {
        if (!isset($this->nodes[$id])) {
            throw new InvalidArgumentException(sprintf('There is no node with id "%s".', $id));
        }

        return $this->nodes[$id];
    }

    /**
     * Returns all nodes.
     *
     * @return ServiceReferenceGraphNode[]
     */
    public function getNodes()
    {
        return $this->nodes;
    }

    /**
     * Clears all nodes.
     */
    public function clear()
    {
        $this->nodes = array();
    }

    /**
     * Connects 2 nodes together in the Graph.
     *
     * @param string $sourceId
     * @param mixed  $sourceValue
     * @param string $destId
     * @param mixed  $destValue
     * @param string $reference
     * @param bool   $lazy
     */
    public function connect($sourceId, $sourceValue, $destId, $destValue = null, $reference = null/*, bool $lazy = false*/)
    {
        if (func_num_args() >= 6) {
            $lazy = func_get_arg(5);
        } else {
            if (__CLASS__ !== get_class($this)) {
                $r = new \ReflectionMethod($this, __FUNCTION__);
                if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                    @trigger_error(sprintf('Method %s() will have a 6th `bool $lazy = false` argument in version 4.0. Not defining it is deprecated since Symfony 3.3.', __METHOD__), E_USER_DEPRECATED);
                }
            }
            $lazy = false;
        }

        if (null === $sourceId || null === $destId) {
            return;
        }

        $sourceNode = $this->createNode($sourceId, $sourceValue);
        $destNode = $this->createNode($destId, $destValue);
        $edge = new ServiceReferenceGraphEdge($sourceNode, $destNode, $reference, $lazy);

        $sourceNode->addOutEdge($edge);
        $destNode->addInEdge($edge);
    }

    /**
     * Creates a graph node.
     *
     * @param string $id
     * @param mixed  $value
     *
     * @return ServiceReferenceGraphNode
     */
    private function createNode($id, $value)
    {
        if (isset($this->nodes[$id]) && $this->nodes[$id]->getValue() === $value) {
            return $this->nodes[$id];
        }

        return $this->nodes[$id] = new ServiceReferenceGraphNode($id, $value);
    }
}

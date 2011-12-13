<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks your services for circular references
 *
 * References from method calls are ignored since we might be able to resolve
 * these references depending on the order in which services are called.
 *
 * Circular reference from method calls will only be detected at run-time.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CheckCircularReferencesPass implements CompilerPassInterface
{
    private $currentId;
    private $currentPath;

    /**
     * Checks the ContainerBuilder object for circular references.
     *
     * @param ContainerBuilder $container The ContainerBuilder instances
     */
    public function process(ContainerBuilder $container)
    {
        $graph = $container->getCompiler()->getServiceReferenceGraph();

        foreach ($graph->getNodes() as $id => $node) {
            $this->currentId = $id;
            $this->currentPath = array($id);

            $this->checkOutEdges($node->getOutEdges());
        }
    }

    /**
     * Checks for circular references.
     *
     * @param array $edges An array of Nodes
     *
     * @throws \RuntimeException When a circular reference is found.
     */
    private function checkOutEdges(array $edges)
    {
        foreach ($edges as $edge) {
            $node = $edge->getDestNode();
            $this->currentPath[] = $id = $node->getId();

            if ($this->currentId === $id) {
                throw new ServiceCircularReferenceException($this->currentId, $this->currentPath);
            }

            $this->checkOutEdges($node->getOutEdges());
            array_pop($this->currentPath);
        }
    }
}

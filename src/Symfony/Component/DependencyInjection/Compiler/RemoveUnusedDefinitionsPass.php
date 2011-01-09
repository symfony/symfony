<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Removes unused service definitions from the container.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RemoveUnusedDefinitionsPass implements RepeatablePassInterface
{
    protected $repeatedPass;
    protected $graph;

    public function setRepeatedPass(RepeatedPass $repeatedPass)
    {
        $this->repeatedPass = $repeatedPass;
    }

    public function process(ContainerBuilder $container)
    {
        $this->graph = $this->repeatedPass->getCompiler()->getServiceReferenceGraph();

        $hasChanged = false;
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isPublic()) {
                continue;
            }

            if ($this->graph->hasNode($id)) {
                $edges = $this->graph->getNode($id)->getInEdges();
                $referencingAliases = array();
                $sourceIds = array();
                foreach ($edges as $edge) {
                    $node = $edge->getSourceNode();
                    $sourceIds[] = $node->getId();

                    if ($node->isAlias()) {
                        $referencingAlias[] = $node->getValue();
                    }
                }
                $isReferenced = (count(array_unique($sourceIds)) - count($referencingAliases)) > 0;
            } else {
                $referencingAliases = array();
                $isReferenced = false;
            }

            if (1 === count($referencingAliases) && false === $isReferenced) {
                $container->setDefinition((string) reset($referencingAliases), $definition);
                $definition->setPublic(true);
                $container->remove($id);
            } else if (0 === count($referencingAliases) && false === $isReferenced) {
                $container->remove($id);
                $hasChanged = true;
            }
        }

        if ($hasChanged) {
            $this->repeatedPass->setRepeat();
        }
    }
}
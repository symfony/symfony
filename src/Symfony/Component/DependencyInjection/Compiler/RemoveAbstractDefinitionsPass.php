<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes abstract Definitions
 *
 */
class RemoveAbstractDefinitionsPass implements CompilerPassInterface
{
    /**
     * Removes abstract definitions from the ContainerBuilder
     *
     * @param ContainerBuilder $container 
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract()) {
                $container->remove($id);
            }
        }
    }
}
<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes unused service definitions from the container
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RemoveUnusedDefinitionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $hasChanged = false;
        $aliases = $container->getAliases();
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isPublic()) {
                continue;
            }

            if (!in_array($id, $aliases, true) && !$this->isReferenced($container, $id)) {
                $container->remove($id);
                $hasChanged = true;
            }
        }

        if ($hasChanged) {
            $this->process($container);
        }
    }

    protected function isReferenced(ContainerBuilder $container, $id)
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($this->isReferencedByArgument($id, $definition->getArguments())) {
                return true;
            }

            foreach ($definition->getMethodCalls() as $arguments)
            {
                if ($this->isReferencedByArgument($id, $arguments)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isReferencedByArgument($id, $argument)
    {
        if (is_array($argument)) {
            foreach ($argument as $arg) {
                if ($this->isReferencedByArgument($id, $arg)) {
                    return true;
                }
            }
        } else if ($argument instanceof Reference) {
            if ($id === (string) $argument) {
                return true;
            }
        }

        return false;
    }
}
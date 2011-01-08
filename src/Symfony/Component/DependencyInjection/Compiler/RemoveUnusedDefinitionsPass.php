<?php

namespace Symfony\Component\DependencyInjection\Compiler;

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

    public function setRepeatedPass(RepeatedPass $repeatedPass)
    {
        $this->repeatedPass = $repeatedPass;
    }

    public function process(ContainerBuilder $container)
    {
        $hasChanged = false;
        $aliases = $container->getAliases();
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isPublic()) {
                continue;
            }

            $referencingAliases = array_keys($aliases, $id, true);
            $isReferenced = $this->isReferenced($container, $id);

            if (1 === count($referencingAliases) && false === $isReferenced) {
                $container->setDefinition(reset($referencingAliases), $definition);
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

    protected function isReferenced(ContainerBuilder $container, $id)
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($this->isReferencedByArgument($id, $definition->getArguments())) {
                return true;
            }

            if ($this->isReferencedByArgument($id, $definition->getMethodCalls())) {
                return true;
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
        } else if ($argument instanceof Definition) {
            if ($this->isReferencedByArgument($id, $argument->getArguments())) {
                return true;
            }

            if ($this->isReferencedByArgument($id, $argument->getMethodCalls())) {
                return true;
            }
        }

        return false;
    }
}
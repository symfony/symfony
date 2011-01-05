<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
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
 * Replaces all references to aliases with references to the actual service.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResolveReferencesToAliasesPass implements CompilerPassInterface
{
    protected $container;

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;

        foreach ($container->getDefinitions() as $id => $definition)
        {
            $definition->setArguments($this->processArguments($definition->getArguments()));
            $definition->setMethodCalls($this->processArguments($definition->getMethodCalls()));
        }
    }

    protected function processArguments(array $arguments)
    {
        foreach ($arguments as $k => $argument) {
            if (is_array($argument)) {
                $arguments[$k] = $this->processArguments($argument);
            } else if ($argument instanceof Reference) {
                $defId = $this->getDefinitionId($id = (string) $argument);

                if ($defId !== $id) {
                    $arguments[$k] = new Reference($defId, $argument->getInvalidBehavior());
                }
            }
        }

        return $arguments;
    }

    protected function getDefinitionId($id)
    {
        if ($this->container->hasAlias($id)) {
            return $this->getDefinitionId($this->container->getAlias($id));
        }

        return $id;
    }
}
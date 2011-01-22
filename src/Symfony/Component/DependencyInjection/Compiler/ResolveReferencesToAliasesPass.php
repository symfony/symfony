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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

        foreach ($container->getAliases() as $id => $alias) {
            $aliasId = (string) $alias;
            if ($aliasId !== $defId = $this->getDefinitionId($aliasId)) {
                $container->setAlias($id, new Alias($defId, $alias->isPublic()));
            }
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
                    $arguments[$k] = new Reference($defId, $argument->getInvalidBehavior(), $argument->isStrict());
                }
            }
        }

        return $arguments;
    }

    protected function getDefinitionId($id)
    {
        if ($this->container->hasAlias($id)) {
            return $this->getDefinitionId((string) $this->container->getAlias($id));
        }

        return $id;
    }
}
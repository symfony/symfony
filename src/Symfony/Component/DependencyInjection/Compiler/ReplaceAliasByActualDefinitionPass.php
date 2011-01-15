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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces aliases with actual service definitions, effectively removing these
 * aliases.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ReplaceAliasByActualDefinitionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getAliases() as $id => $alias) {
            $aliasId = (string) $alias;

            $definition = $container->getDefinition($aliasId = (string) $alias);

            if ($definition->isPublic()) {
                continue;
            }

            $definition->setPublic(true);
            $container->setDefinition($id, $definition);
            $container->remove($aliasId);

            $this->updateReferences($container, $aliasId, $id);

            // we have to restart the process due to concurrent modification of
            // the container
            $this->process($container);

            break;
        }
    }

    protected function updateReferences($container, $currentId, $newId)
    {
        foreach ($container->getAliases() as $id => $alias) {
            if ($currentId === (string) $alias) {
                $container->setAlias($id, $newId);
            }
        }

        foreach ($container->getDefinitions() as $definition) {
            $definition->setArguments(
                $this->updateArgumentReferences($definition->getArguments(), $currentId, $newId)
            );

            $definition->setMethodCalls(
                $this->updateArgumentReferences($definition->getMethodCalls(), $currentId, $newId)
            );
        }
    }

    protected function updateArgumentReferences(array $arguments, $currentId, $newId)
    {
        foreach ($arguments as $k => $argument) {
            if (is_array($argument)) {
                $arguments[$k] = $this->updateArgumentReferences($argument, $currentId, $newId);
            } else if ($argument instanceof Reference) {
                if ($currentId === (string) $argument) {
                    $arguments[$k] = new Reference($newId, $argument->getInvalidBehavior());
                }
            }
        }

        return $arguments;
    }
}
<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;

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
 * Inline service definitions where this is possible.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InlineServiceDefinitionsPass implements CompilerPassInterface
{
    protected $aliasMap;

    public function process(ContainerBuilder $container)
    {
        $this->aliasMap = array();
        foreach ($container->getAliases() as $id => $alias) {
            if (!$alias->isPublic()) {
                continue;
            }

            $this->aliasMap[$id] = (string) $alias;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            $definition->setArguments(
                $this->inlineArguments($container, $definition->getArguments())
            );

            $definition->setMethodCalls(
                $this->inlineArguments($container, $definition->getMethodCalls())
            );
        }
    }

    protected function inlineArguments(ContainerBuilder $container, array $arguments)
    {
        foreach ($arguments as $k => $argument) {
            if (is_array($argument)) {
                $arguments[$k] = $this->inlineArguments($container, $argument);
            } else if ($argument instanceof Reference) {
                if (!$container->hasDefinition($id = (string) $argument)) {
                    continue;
                }

                if ($this->isInlinableDefinition($container, $id, $definition = $container->getDefinition($id))) {
                    $arguments[$k] = $definition;
                }
            }
        }

        return $arguments;
    }

    protected function isInlinableDefinition(ContainerBuilder $container, $id, Definition $definition)
    {
        if (!$definition->isShared()) {
            return true;
        }

        if ($definition->isPublic()) {
            return false;
        }

        $references = count(array_keys($this->aliasMap, $id, true));
        foreach ($container->getDefinitions() as $cDefinition)
        {
            if ($references > 1) {
                break;
            }

            if ($this->isReferencedByArgument($id, $cDefinition->getArguments())) {
                $references += 1;
                continue;
            }

            foreach ($cDefinition->getMethodCalls() as $call) {
                if ($this->isReferencedByArgument($id, $call[1])) {
                    $references += 1;
                    continue 2;
                }
            }
        }

        return $references <= 1;
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
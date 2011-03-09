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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Inline service definitions where this is possible.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InlineServiceDefinitionsPass implements RepeatablePassInterface
{
    protected $repeatedPass;
    protected $graph;

    /**
     * {@inheritDoc}
     */
    public function setRepeatedPass(RepeatedPass $repeatedPass)
    {
        $this->repeatedPass = $repeatedPass;
    }

    /**
     * Processes the ContainerBuilder for inline service definitions.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->graph = $container->getCompiler()->getServiceReferenceGraph();

        foreach ($container->getDefinitions() as $definition) {
            $definition->setArguments(
                $this->inlineArguments($container, $definition->getArguments())
            );

            $definition->setMethodCalls(
                $this->inlineArguments($container, $definition->getMethodCalls())
            );
        }
    }

    /**
     * Processes inline arguments.
     *
     * @param ContainerBuilder $container The ContainerBuilder
     * @param array $arguments An array of arguments
     */
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
                    if (ContainerInterface::SCOPE_PROTOTYPE !== $definition->getScope()) {
                        $arguments[$k] = $definition;
                    } else {
                        $arguments[$k] = clone $definition;
                    }
                }
            } else if ($argument instanceof Definition) {
                $argument->setArguments($this->inlineArguments($container, $argument->getArguments()));
                $argument->setMethodCalls($this->inlineArguments($container, $argument->getMethodCalls()));
            }
        }

        return $arguments;
    }

    /**
     * Checks if the definition is inlineable.
     *
     * @param ContainerBuilder $container
     * @param string $id
     * @param Definition $definition
     * @return boolean If the definition is inlineable
     */
    protected function isInlinableDefinition(ContainerBuilder $container, $id, Definition $definition)
    {
        if (ContainerInterface::SCOPE_PROTOTYPE === $definition->getScope()) {
            return true;
        }

        if ($definition->isPublic()) {
            return false;
        }

        if (!$this->graph->hasNode($id)) {
            return true;
        }

        $ids = array();
        foreach ($this->graph->getNode($id)->getInEdges() as $edge) {
            $ids[] = $edge->getSourceNode()->getId();
        }

        if (count(array_unique($ids)) > 1) {
            return false;
        }

        return $container->getDefinition(reset($ids))->getScope() === $definition->getScope();
    }
}

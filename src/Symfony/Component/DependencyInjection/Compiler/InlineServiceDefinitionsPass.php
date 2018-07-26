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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Inline service definitions where this is possible.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InlineServiceDefinitionsPass implements RepeatablePassInterface
{
    private $graph;
    private $compiler;
    private $formatter;
    private $currentId;

    /**
     * {@inheritdoc}
     */
    public function setRepeatedPass(RepeatedPass $repeatedPass)
    {
        // no-op for BC
    }

    /**
     * Processes the ContainerBuilder for inline service definitions.
     */
    public function process(ContainerBuilder $container)
    {
        $this->compiler = $container->getCompiler();
        $this->formatter = $this->compiler->getLoggingFormatter();
        $this->graph = $this->compiler->getServiceReferenceGraph();

        $container->setDefinitions($this->inlineArguments($container, $container->getDefinitions(), true));
    }

    /**
     * Processes inline arguments.
     *
     * @param ContainerBuilder $container The ContainerBuilder
     * @param array            $arguments An array of arguments
     * @param bool             $isRoot    If we are processing the root definitions or not
     *
     * @return array
     */
    private function inlineArguments(ContainerBuilder $container, array $arguments, $isRoot = false)
    {
        foreach ($arguments as $k => $argument) {
            if ($isRoot) {
                $this->currentId = $k;
            }
            if (\is_array($argument)) {
                $arguments[$k] = $this->inlineArguments($container, $argument);
            } elseif ($argument instanceof Reference) {
                if (!$container->hasDefinition($id = (string) $argument)) {
                    continue;
                }

                if ($this->isInlineableDefinition($container, $id, $definition = $container->getDefinition($id))) {
                    $this->compiler->addLogMessage($this->formatter->formatInlineService($this, $id, $this->currentId));

                    if ($definition->isShared() && ContainerInterface::SCOPE_PROTOTYPE !== $definition->getScope(false)) {
                        $arguments[$k] = $definition;
                    } else {
                        $arguments[$k] = clone $definition;
                    }
                }
            } elseif ($argument instanceof Definition) {
                $argument->setArguments($this->inlineArguments($container, $argument->getArguments()));
                $argument->setMethodCalls($this->inlineArguments($container, $argument->getMethodCalls()));
                $argument->setProperties($this->inlineArguments($container, $argument->getProperties()));

                $configurator = $this->inlineArguments($container, array($argument->getConfigurator()));
                $argument->setConfigurator($configurator[0]);

                $factory = $this->inlineArguments($container, array($argument->getFactory()));
                $argument->setFactory($factory[0]);
            }
        }

        return $arguments;
    }

    /**
     * Checks if the definition is inlineable.
     *
     * @param ContainerBuilder $container
     * @param string           $id
     * @param Definition       $definition
     *
     * @return bool If the definition is inlineable
     */
    private function isInlineableDefinition(ContainerBuilder $container, $id, Definition $definition)
    {
        if ($definition->isDeprecated() || $definition->isLazy() || $definition->isSynthetic()) {
            return false;
        }

        if (!$definition->isShared() || ContainerInterface::SCOPE_PROTOTYPE === $definition->getScope(false)) {
            return true;
        }

        if ($definition->isPublic()) {
            return false;
        }

        if (!$this->graph->hasNode($id)) {
            return true;
        }

        if ($this->currentId == $id) {
            return false;
        }

        $ids = array();
        foreach ($this->graph->getNode($id)->getInEdges() as $edge) {
            $ids[] = $edge->getSourceNode()->getId();
        }

        if (\count(array_unique($ids)) > 1) {
            return false;
        }

        if (\count($ids) > 1 && \is_array($factory = $definition->getFactory()) && ($factory[0] instanceof Reference || $factory[0] instanceof Definition)) {
            return false;
        }

        if (\count($ids) > 1 && $definition->getFactoryService(false)) {
            return false;
        }

        return $container->getDefinition(reset($ids))->getScope(false) === $definition->getScope(false);
    }
}

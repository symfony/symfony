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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Run this pass before passes that need to know more about the relation of
 * your services.
 *
 * This class will populate the ServiceReferenceGraph with information. You can
 * retrieve the graph in other passes from the compiler.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AnalyzeServiceReferencesPass implements RepeatablePassInterface
{
    private $graph;
    private $container;
    private $currentId;
    private $currentDefinition;
    private $repeatedPass;
    private $onlyConstructorArguments;

    /**
     * Constructor.
     *
     * @param boolean $ignoreMethodCalls Sets this Service Reference pass to ignore method calls
     */
    public function __construct($onlyConstructorArguments = false)
    {
        $this->onlyConstructorArguments = (Boolean) $onlyConstructorArguments;
    }

    /**
     * {@inheritDoc}
     */
    public function setRepeatedPass(RepeatedPass $repeatedPass) {
        $this->repeatedPass = $repeatedPass;
    }

    /**
     * Processes a ContainerBuilder object to populate the service reference graph.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->graph     = $container->getCompiler()->getServiceReferenceGraph();
        $this->graph->clear();

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isSynthetic() || $definition->isAbstract()) {
                continue;
            }

            $this->currentId = $id;
            $this->currentDefinition = $definition;
            $this->processArguments($definition->getArguments());

            if (!$this->onlyConstructorArguments) {
                $this->processArguments($definition->getMethodCalls());
                $this->processArguments($definition->getProperties());
            }
        }

        foreach ($container->getAliases() as $id => $alias) {
            $this->graph->connect($id, $alias, (string) $alias, $this->getDefinition((string) $alias), null);
        }
    }

    /**
     * Processes service definitions for arguments to find relationships for the service graph.
     *
     * @param array $arguments An array of Reference or Definition objects relating to service definitions
     */
    private function processArguments(array $arguments)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $this->processArguments($argument);
            } else if ($argument instanceof Reference) {
                $this->graph->connect(
                    $this->currentId,
                    $this->currentDefinition,
                    (string) $argument,
                    $this->getDefinition((string) $argument),
                    $argument
                );
            } else if ($argument instanceof Definition) {
                $this->processArguments($argument->getArguments());
                $this->processArguments($argument->getMethodCalls());
                $this->processArguments($argument->getProperties());
            }
        }
    }

    /**
     * Returns a service definition given the full name or an alias.
     *
     * @param string $id A full id or alias for a service definition.
     * @return Definition The definition related to the supplied id
     */
    private function getDefinition($id)
    {
        while ($this->container->hasAlias($id)) {
            $id = (string) $this->container->getAlias($id);
        }

        if (!$this->container->hasDefinition($id)) {
            return null;
        }

        return $this->container->getDefinition($id);
    }
}

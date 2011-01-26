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
    protected $graph;
    protected $container;
    protected $currentId;
    protected $currentDefinition;
    protected $repeatedPass;
    protected $ignoreMethodCalls;

    public function __construct($ignoreMethodCalls = false)
    {
        $this->ignoreMethodCalls = (Boolean) $ignoreMethodCalls;
    }

    public function setRepeatedPass(RepeatedPass $repeatedPass) {
        $this->repeatedPass = $repeatedPass;
    }

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

            if (!$this->ignoreMethodCalls) {
                $this->processArguments($definition->getMethodCalls());
            }
        }

        foreach ($container->getAliases() as $id => $alias) {
            $this->graph->connect($id, $alias, (string) $alias, $this->getDefinition((string) $alias), null);
        }
    }

    protected function processArguments(array $arguments)
    {
        foreach ($arguments as $k => $argument) {
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
            }
        }
    }

    protected function getDefinition($id)
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
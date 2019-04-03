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

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Run this pass before passes that need to know more about the relation of
 * your services.
 *
 * This class will populate the ServiceReferenceGraph with information. You can
 * retrieve the graph in other passes from the compiler.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AnalyzeServiceReferencesPass extends AbstractRecursivePass implements RepeatablePassInterface
{
    private $graph;
    private $currentDefinition;
    private $onlyConstructorArguments;
    private $hasProxyDumper;
    private $lazy;
    private $expressionLanguage;
    private $byConstructor;
    private $definitions;
    private $aliases;

    /**
     * @param bool $onlyConstructorArguments Sets this Service Reference pass to ignore method calls
     */
    public function __construct(bool $onlyConstructorArguments = false, bool $hasProxyDumper = true)
    {
        $this->onlyConstructorArguments = $onlyConstructorArguments;
        $this->hasProxyDumper = $hasProxyDumper;
        $this->enableExpressionProcessing();
    }

    /**
     * {@inheritdoc}
     */
    public function setRepeatedPass(RepeatedPass $repeatedPass)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2.', __METHOD__), E_USER_DEPRECATED);
    }

    /**
     * Processes a ContainerBuilder object to populate the service reference graph.
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->graph = $container->getCompiler()->getServiceReferenceGraph();
        $this->graph->clear();
        $this->lazy = false;
        $this->byConstructor = false;
        $this->definitions = $container->getDefinitions();
        $this->aliases = $container->getAliases();

        foreach ($this->aliases as $id => $alias) {
            $targetId = $this->getDefinitionId((string) $alias);
            $this->graph->connect($id, $alias, $targetId, null !== $targetId ? $this->container->getDefinition($targetId) : null, null);
        }

        try {
            parent::process($container);
        } finally {
            $this->aliases = $this->definitions = [];
        }
    }

    protected function processValue($value, $isRoot = false)
    {
        $lazy = $this->lazy;
        $inExpression = $this->inExpression();

        if ($value instanceof ArgumentInterface) {
            $this->lazy = true;
            parent::processValue($value->getValues());
            $this->lazy = $lazy;

            return $value;
        }
        if ($value instanceof Reference) {
            $targetId = $this->getDefinitionId((string) $value);
            $targetDefinition = null !== $targetId ? $this->container->getDefinition($targetId) : null;

            $this->graph->connect(
                $this->currentId,
                $this->currentDefinition,
                $targetId,
                $targetDefinition,
                $value,
                $this->lazy || ($this->hasProxyDumper && $targetDefinition && $targetDefinition->isLazy()),
                ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE === $value->getInvalidBehavior(),
                $this->byConstructor
            );

            if ($inExpression) {
                $this->graph->connect(
                    '.internal.reference_in_expression',
                    null,
                    $targetId,
                    $targetDefinition,
                    $value,
                    $this->lazy || ($targetDefinition && $targetDefinition->isLazy()),
                    true
               );
            }

            return $value;
        }
        if (!$value instanceof Definition) {
            return parent::processValue($value, $isRoot);
        }
        if ($isRoot) {
            if ($value->isSynthetic() || $value->isAbstract()) {
                return $value;
            }
            $this->currentDefinition = $value;
        } elseif ($this->currentDefinition === $value) {
            return $value;
        }
        $this->lazy = false;

        $byConstructor = $this->byConstructor;
        $this->byConstructor = true;
        $this->processValue($value->getFactory());
        $this->processValue($value->getArguments());

        $properties = $value->getProperties();
        $setters = $value->getMethodCalls();

        // Any references before a "wither" are part of the constructor-instantiation graph
        $lastWitherIndex = null;
        foreach ($setters as $k => $call) {
            if ($call[2] ?? false) {
                $lastWitherIndex = $k;
            }
        }

        if (null !== $lastWitherIndex) {
            $this->processValue($properties);
            $setters = $properties = [];

            foreach ($value->getMethodCalls() as $k => $call) {
                if (null === $lastWitherIndex) {
                    $setters[] = $call;
                    continue;
                }

                if ($lastWitherIndex === $k) {
                    $lastWitherIndex = null;
                }

                $this->processValue($call);
            }
        }

        $this->byConstructor = $byConstructor;

        if (!$this->onlyConstructorArguments) {
            $this->processValue($properties);
            $this->processValue($setters);
            $this->processValue($value->getConfigurator());
        }
        $this->lazy = $lazy;

        return $value;
    }

    private function getDefinitionId(string $id): ?string
    {
        while (isset($this->aliases[$id])) {
            $id = (string) $this->aliases[$id];
        }

        return isset($this->definitions[$id]) ? $id : null;
    }
}

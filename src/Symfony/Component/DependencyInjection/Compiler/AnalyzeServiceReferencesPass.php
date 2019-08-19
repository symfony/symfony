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
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Run this pass before passes that need to know more about the relation of
 * your services.
 *
 * This class will populate the ServiceReferenceGraph with information. You can
 * retrieve the graph in other passes from the compiler.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
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

    /**
     * @param bool $onlyConstructorArguments Sets this Service Reference pass to ignore method calls
     */
    public function __construct($onlyConstructorArguments = false, $hasProxyDumper = true)
    {
        $this->onlyConstructorArguments = (bool) $onlyConstructorArguments;
        $this->hasProxyDumper = (bool) $hasProxyDumper;
    }

    /**
     * {@inheritdoc}
     */
    public function setRepeatedPass(RepeatedPass $repeatedPass)
    {
        // no-op for BC
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

        foreach ($container->getAliases() as $id => $alias) {
            $targetId = $this->getDefinitionId((string) $alias);
            $this->graph->connect($id, $alias, $targetId, $this->getDefinition($targetId), null);
        }

        parent::process($container);
    }

    protected function processValue($value, $isRoot = false)
    {
        $lazy = $this->lazy;

        if ($value instanceof ArgumentInterface) {
            $this->lazy = true;
            parent::processValue($value->getValues());
            $this->lazy = $lazy;

            return $value;
        }
        if ($value instanceof Expression) {
            $this->getExpressionLanguage()->compile((string) $value, ['this' => 'container']);

            return $value;
        }
        if ($value instanceof Reference) {
            $targetId = $this->getDefinitionId((string) $value);
            $targetDefinition = $this->getDefinition($targetId);

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
        $this->byConstructor = $isRoot || $byConstructor;
        $this->processValue($value->getFactory());
        $this->processValue($value->getArguments());
        $this->byConstructor = $byConstructor;

        if (!$this->onlyConstructorArguments) {
            $this->processValue($value->getProperties());
            $this->processValue($value->getMethodCalls());
            $this->processValue($value->getConfigurator());
        }
        $this->lazy = $lazy;

        return $value;
    }

    /**
     * Returns a service definition given the full name or an alias.
     *
     * @param string $id A full id or alias for a service definition
     *
     * @return Definition|null The definition related to the supplied id
     */
    private function getDefinition($id)
    {
        return null === $id ? null : $this->container->getDefinition($id);
    }

    private function getDefinitionId($id)
    {
        while ($this->container->hasAlias($id)) {
            $id = (string) $this->container->getAlias($id);
        }

        if (!$this->container->hasDefinition($id)) {
            return null;
        }

        return $this->container->normalizeId($id);
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists(ExpressionLanguage::class)) {
                throw new RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }

            $providers = $this->container->getExpressionLanguageProviders();
            $this->expressionLanguage = new ExpressionLanguage(null, $providers, function ($arg) {
                if ('""' === substr_replace($arg, '', 1, -1)) {
                    $id = stripcslashes(substr($arg, 1, -1));
                    $id = $this->getDefinitionId($id);

                    $this->graph->connect(
                        $this->currentId,
                        $this->currentDefinition,
                        $id,
                        $this->getDefinition($id)
                    );
                }

                return sprintf('$this->get(%s)', $arg);
            });
        }

        return $this->expressionLanguage;
    }
}

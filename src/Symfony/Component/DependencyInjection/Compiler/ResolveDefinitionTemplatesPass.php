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
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ExceptionInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * This replaces all DefinitionDecorator instances with their equivalent fully
 * merged Definition instance.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveDefinitionTemplatesPass implements CompilerPassInterface
{
    private $compiler;
    private $formatter;
    private $currentId;

    /**
     * Process the ContainerBuilder to replace DefinitionDecorator instances with their real Definition instances.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->compiler = $container->getCompiler();
        $this->formatter = $this->compiler->getLoggingFormatter();

        $container->setDefinitions($this->resolveArguments($container, $container->getDefinitions(), true));
    }

    /**
     * Resolves definition decorator arguments.
     *
     * @param ContainerBuilder $container The ContainerBuilder
     * @param array            $arguments An array of arguments
     * @param bool             $isRoot    If we are processing the root definitions or not
     *
     * @return array
     */
    private function resolveArguments(ContainerBuilder $container, array $arguments, $isRoot = false)
    {
        foreach ($arguments as $k => $argument) {
            if ($isRoot) {
                // yes, we are specifically fetching the definition from the
                // container to ensure we are not operating on stale data
                $arguments[$k] = $argument = $container->getDefinition($k);
                $this->currentId = $k;
            }
            if (is_array($argument)) {
                $arguments[$k] = $this->resolveArguments($container, $argument);
            } elseif ($argument instanceof Definition) {
                if ($argument instanceof DefinitionDecorator) {
                    $arguments[$k] = $argument = $this->resolveDefinition($container, $argument);
                    if ($isRoot) {
                        $container->setDefinition($k, $argument);
                    }
                }
                $argument->setArguments($this->resolveArguments($container, $argument->getArguments()));
                $argument->setMethodCalls($this->resolveArguments($container, $argument->getMethodCalls()));
                $argument->setProperties($this->resolveArguments($container, $argument->getProperties()));

                $configurator = $this->resolveArguments($container, array($argument->getConfigurator()));
                $argument->setConfigurator($configurator[0]);

                $factory = $this->resolveArguments($container, array($argument->getFactory()));
                $argument->setFactory($factory[0]);
            }
        }

        return $arguments;
    }

    /**
     * Resolves the definition.
     *
     * @param ContainerBuilder    $container  The ContainerBuilder
     * @param DefinitionDecorator $definition
     *
     * @return Definition
     *
     * @throws \RuntimeException When the definition is invalid
     */
    private function resolveDefinition(ContainerBuilder $container, DefinitionDecorator $definition)
    {
        try {
            return $this->doResolveDefinition($container, $definition);
        } catch (ExceptionInterface $e) {
            $r = new \ReflectionProperty($e, 'message');
            $r->setAccessible(true);
            $r->setValue($e, sprintf('Service "%s": %s', $this->currentId, $e->getMessage()));

            throw $e;
        }
    }

    private function doResolveDefinition(ContainerBuilder $container, DefinitionDecorator $definition)
    {
        if (!$container->has($parent = $definition->getParent())) {
            throw new RuntimeException(sprintf('Parent definition "%s" does not exist.', $parent));
        }

        $parentDef = $container->findDefinition($parent);
        if ($parentDef instanceof DefinitionDecorator) {
            $id = $this->currentId;
            $this->currentId = $parent;
            $parentDef = $this->resolveDefinition($container, $parentDef);
            $container->setDefinition($parent, $parentDef);
            $this->currentId = $id;
        }

        $this->compiler->addLogMessage($this->formatter->formatResolveInheritance($this, $this->currentId, $parent));

        return $definition->resolveChanges($parentDef);
    }
}

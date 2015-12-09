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
        if (!$container->hasDefinition($parent = $definition->getParent())) {
            throw new RuntimeException(sprintf('The parent definition "%s" defined for definition "%s" does not exist.', $parent, $this->currentId));
        }

        $parentDef = $container->getDefinition($parent);
        if ($parentDef instanceof DefinitionDecorator) {
            $id = $this->currentId;
            $this->currentId = $parent;
            $parentDef = $this->resolveDefinition($container, $parentDef);
            $container->setDefinition($parent, $parentDef);
            $this->currentId = $id;
        }

        $this->compiler->addLogMessage($this->formatter->formatResolveInheritance($this, $this->currentId, $parent));
        $def = new Definition();

        // merge in parent definition
        // purposely ignored attributes: scope, abstract, tags
        $def->setClass($parentDef->getClass());
        $def->setArguments($parentDef->getArguments());
        $def->setMethodCalls($parentDef->getMethodCalls());
        $def->setProperties($parentDef->getProperties());
        $def->setAutowiringTypes($parentDef->getAutowiringTypes());
        if ($parentDef->getFactoryClass(false)) {
            $def->setFactoryClass($parentDef->getFactoryClass(false));
        }
        if ($parentDef->getFactoryMethod(false)) {
            $def->setFactoryMethod($parentDef->getFactoryMethod(false));
        }
        if ($parentDef->getFactoryService(false)) {
            $def->setFactoryService($parentDef->getFactoryService(false));
        }
        if ($parentDef->isDeprecated()) {
            $def->setDeprecated(true, $parentDef->getDeprecationMessage('%service_id%'));
        }
        $def->setFactory($parentDef->getFactory());
        $def->setConfigurator($parentDef->getConfigurator());
        $def->setFile($parentDef->getFile());
        $def->setPublic($parentDef->isPublic());
        $def->setLazy($parentDef->isLazy());

        // overwrite with values specified in the decorator
        $changes = $definition->getChanges();
        if (isset($changes['class'])) {
            $def->setClass($definition->getClass());
        }
        if (isset($changes['factory_class'])) {
            $def->setFactoryClass($definition->getFactoryClass(false));
        }
        if (isset($changes['factory_method'])) {
            $def->setFactoryMethod($definition->getFactoryMethod(false));
        }
        if (isset($changes['factory_service'])) {
            $def->setFactoryService($definition->getFactoryService(false));
        }
        if (isset($changes['factory'])) {
            $def->setFactory($definition->getFactory());
        }
        if (isset($changes['configurator'])) {
            $def->setConfigurator($definition->getConfigurator());
        }
        if (isset($changes['file'])) {
            $def->setFile($definition->getFile());
        }
        if (isset($changes['public'])) {
            $def->setPublic($definition->isPublic());
        }
        if (isset($changes['lazy'])) {
            $def->setLazy($definition->isLazy());
        }
        if (isset($changes['deprecated'])) {
            $def->setDeprecated($definition->isDeprecated(), $definition->getDeprecationMessage('%service_id%'));
        }
        if (isset($changes['decorated_service'])) {
            $decoratedService = $definition->getDecoratedService();
            if (null === $decoratedService) {
                $def->setDecoratedService($decoratedService);
            } else {
                $def->setDecoratedService($decoratedService[0], $decoratedService[1]);
            }
        }

        // merge arguments
        foreach ($definition->getArguments() as $k => $v) {
            if (is_numeric($k)) {
                $def->addArgument($v);
                continue;
            }

            if (0 !== strpos($k, 'index_')) {
                throw new RuntimeException(sprintf('Invalid argument key "%s" found.', $k));
            }

            $index = (int) substr($k, strlen('index_'));
            $def->replaceArgument($index, $v);
        }

        // merge properties
        foreach ($definition->getProperties() as $k => $v) {
            $def->setProperty($k, $v);
        }

        // append method calls
        if (count($calls = $definition->getMethodCalls()) > 0) {
            $def->setMethodCalls(array_merge($def->getMethodCalls(), $calls));
        }

        // merge autowiring types
        foreach ($definition->getAutowiringTypes() as $autowiringType) {
            $def->addAutowiringType($autowiringType);
        }

        // these attributes are always taken from the child
        $def->setAbstract($definition->isAbstract());
        $def->setScope($definition->getScope(false), false);
        $def->setShared($definition->isShared());
        $def->setTags($definition->getTags());

        return $def;
    }
}

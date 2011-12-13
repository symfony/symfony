<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This replaces all DefinitionDecorator instances with their equivalent fully
 * merged Definition instance.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResolveDefinitionTemplatesPass implements CompilerPassInterface
{
    private $container;
    private $compiler;
    private $formatter;

    /**
     * Process the ContainerBuilder to replace DefinitionDecorator instances with their real Definition instances.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->compiler = $container->getCompiler();
        $this->formatter = $this->compiler->getLoggingFormatter();

        foreach (array_keys($container->getDefinitions()) as $id) {
            // yes, we are specifically fetching the definition from the
            // container to ensure we are not operating on stale data
            $definition = $container->getDefinition($id);
            if (!$definition instanceof DefinitionDecorator || $definition->isAbstract()) {
                continue;
            }

            $this->resolveDefinition($id, $definition);
        }
    }

    /**
     * Resolves the definition
     *
     * @param string              $id         The definition identifier
     * @param DefinitionDecorator $definition
     *
     * @return Definition
     */
    private function resolveDefinition($id, DefinitionDecorator $definition)
    {
        if (!$this->container->hasDefinition($parent = $definition->getParent())) {
            throw new \RuntimeException(sprintf('The parent definition "%s" defined for definition "%s" does not exist.', $parent, $id));
        }

        $parentDef = $this->container->getDefinition($parent);
        if ($parentDef instanceof DefinitionDecorator) {
            $parentDef = $this->resolveDefinition($parent, $parentDef);
        }

        $this->compiler->addLogMessage($this->formatter->formatResolveInheritance($this, $id, $parent));
        $def = new Definition();

        // merge in parent definition
        // purposely ignored attributes: scope, abstract, tags
        $def->setClass($parentDef->getClass());
        $def->setArguments($parentDef->getArguments());
        $def->setMethodCalls($parentDef->getMethodCalls());
        $def->setProperties($parentDef->getProperties());
        $def->setFactoryClass($parentDef->getFactoryClass());
        $def->setFactoryMethod($parentDef->getFactoryMethod());
        $def->setFactoryService($parentDef->getFactoryService());
        $def->setConfigurator($parentDef->getConfigurator());
        $def->setFile($parentDef->getFile());
        $def->setPublic($parentDef->isPublic());

        // overwrite with values specified in the decorator
        $changes = $definition->getChanges();
        if (isset($changes['class'])) {
            $def->setClass($definition->getClass());
        }
        if (isset($changes['factory_class'])) {
            $def->setFactoryClass($definition->getFactoryClass());
        }
        if (isset($changes['factory_method'])) {
            $def->setFactoryMethod($definition->getFactoryMethod());
        }
        if (isset($changes['factory_service'])) {
            $def->setFactoryService($definition->getFactoryService());
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

        // merge arguments
        foreach ($definition->getArguments() as $k => $v) {
            if (is_numeric($k)) {
                $def->addArgument($v);
                continue;
            }

            if (0 !== strpos($k, 'index_')) {
                throw new \RuntimeException(sprintf('Invalid argument key "%s" found.', $k));
            }

            $index = (integer) substr($k, strlen('index_'));
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

        // these attributes are always taken from the child
        $def->setAbstract($definition->isAbstract());
        $def->setScope($definition->getScope());
        $def->setTags($definition->getTags());

        // set new definition on container
        $this->container->setDefinition($id, $def);

        return $def;
    }
}

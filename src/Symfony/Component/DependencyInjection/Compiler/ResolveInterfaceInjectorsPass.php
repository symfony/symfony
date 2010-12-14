<?php

namespace Symfony\Component\DependencyInjection\Compiler;

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
 * Resolves interface injectors and inlines them as method calls
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class ResolveInterfaceInjectorsPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $definition) {
            foreach ($container->getInterfaceInjectors() as $injector) {
                if (null !== $definition->getFactoryService()) {
                    continue;
                }
                $defClass = $container->getParameterBag()->resolveValue($definition->getClass());
                $definition->setClass($defClass);
                if ($injector->supports($defClass)) {
                    $injector->processDefinition($definition);
                }
            }
        }
    }
}

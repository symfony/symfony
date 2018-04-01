<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symphony\Component\DependencyInjection\Reference;

/**
 * Registers Twig runtime services.
 */
class RuntimeLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('twig.runtime_loader')) {
            return;
        }

        $definition = $container->getDefinition('twig.runtime_loader');
        $mapping = array();
        foreach ($container->findTaggedServiceIds('twig.runtime', true) as $id => $attributes) {
            $def = $container->getDefinition($id);
            $mapping[$def->getClass()] = new Reference($id);
        }

        $definition->replaceArgument(0, ServiceLocatorTagPass::register($container, $mapping));
    }
}

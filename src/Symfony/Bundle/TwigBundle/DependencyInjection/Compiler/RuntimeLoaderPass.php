<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

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
        foreach ($container->findTaggedServiceIds('twig.runtime') as $id => $attributes) {
            $def = $container->getDefinition($id);

            if ($def->isAbstract()) {
                continue;
            }

            $mapping[$def->getClass()] = new Reference($id);
        }

        $definition->replaceArgument(0, new ServiceLocatorArgument($mapping));
    }
}

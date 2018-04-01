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

use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Exception\LogicException;

/**
 * Adds services tagged twig.loader as Twig loaders.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class TwigLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('twig')) {
            return;
        }

        $prioritizedLoaders = array();
        $found = 0;

        foreach ($container->findTaggedServiceIds('twig.loader', true) as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $prioritizedLoaders[$priority][] = $id;
            ++$found;
        }

        if (!$found) {
            throw new LogicException('No twig loaders found. You need to tag at least one loader with "twig.loader"');
        }

        if (1 === $found) {
            $container->setAlias('twig.loader', $id)->setPrivate(true);
        } else {
            $chainLoader = $container->getDefinition('twig.loader.chain');
            krsort($prioritizedLoaders);

            foreach ($prioritizedLoaders as $loaders) {
                foreach ($loaders as $loader) {
                    $chainLoader->addMethodCall('addLoader', array(new Reference($loader)));
                }
            }

            $container->setAlias('twig.loader', 'twig.loader.chain')->setPrivate(true);
        }
    }
}

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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

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

        $prioritizedLoaders = [];
        $found = 0;

        foreach ($container->findTaggedServiceIds('twig.loader', true) as $id => $attributes) {
            $priority = $attributes[0]['priority'] ?? 0;
            $prioritizedLoaders[$priority][] = $id;
            ++$found;
        }

        if (!$found) {
            throw new LogicException('No twig loaders found. You need to tag at least one loader with "twig.loader".');
        }

        if (1 === $found) {
            $container->setAlias('twig.loader', $id)->setPrivate(true);
        } else {
            $chainLoader = $container->getDefinition('twig.loader.chain');
            krsort($prioritizedLoaders);

            foreach ($prioritizedLoaders as $loaders) {
                foreach ($loaders as $loader) {
                    $chainLoader->addMethodCall('addLoader', [new Reference($loader)]);
                }
            }

            $container->setAlias('twig.loader', 'twig.loader.chain')->setPrivate(true);
        }
    }
}

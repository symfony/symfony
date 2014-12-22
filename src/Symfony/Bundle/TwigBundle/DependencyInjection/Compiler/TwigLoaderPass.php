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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;

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

        // register additional template loaders
        $loaderIds = $container->findTaggedServiceIds('twig.loader');

        if (count($loaderIds) === 0) {
            throw new LogicException('No twig loaders found. You need to tag at least one loader with "twig.loader"');
        }

        if (count($loaderIds) === 1) {
            $container->setAlias('twig.loader', key($loaderIds));
        } else {
            $chainLoader = $container->getDefinition('twig.loader.chain');
            foreach (array_keys($loaderIds) as $id) {
                $chainLoader->addMethodCall('addLoader', array(new Reference($id)));
            }
            $container->setAlias('twig.loader', 'twig.loader.chain');
        }
    }
}

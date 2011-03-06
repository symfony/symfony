<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds services tagged as workers to the asset factory.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class AssetFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('assetic.asset_factory')) {
            return;
        }

        $factory = $container->getDefinition('assetic.asset_factory');
        foreach ($container->findTaggedServiceIds('assetic.factory_worker') as $id => $attr) {
            $factory->addMethodCall('addWorker', array(new Reference($id)));
        }
    }
}

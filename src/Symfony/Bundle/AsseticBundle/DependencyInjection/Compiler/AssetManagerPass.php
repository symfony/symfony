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
 * Adds services tagged as assets to the asset manager.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class AssetManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('assetic.asset_manager')) {
            return;
        }

        $am = $container->getDefinition('assetic.asset_manager');

        // add assets
        foreach ($container->findTaggedServiceIds('assetic.asset') as $id => $attributes) {
            foreach ($attributes as $attr) {
                if (isset($attr['alias'])) {
                    $am->addMethodCall('set', array($attr['alias'], new Reference($id)));
                }
            }
        }

        // add loaders
        $loaders = array();
        foreach ($container->findTaggedServiceIds('assetic.formula_loader') as $id => $attributes) {
            foreach ($attributes as $attr) {
                if (isset($attr['alias'])) {
                    $loaders[$attr['alias']] = new Reference($id);
                }
            }
        }
        $am->replaceArgument(1, $loaders);

        // add resources
        foreach ($container->findTaggedServiceIds('assetic.formula_resource') as $id => $attributes) {
            foreach ($attributes as $attr) {
                if (isset($attr['loader'])) {
                    $am->addMethodCall('addResource', array(new Reference($id), $attr['loader']));
                }
            }
        }
    }
}

<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class TemplatingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('assetic.asset_manager')) {
            return;
        }

        $am = $container->getDefinition('assetic.asset_manager');
        $engines = $container->getParameterBag()->resolveValue($container->getParameter('templating.engines'));

        if (in_array('twig', $engines)) {
            $am->addMethodCall('addCacheFile', array('%kernel.cache_dir%/assetic_twig_assets.php'));
        } else {
            foreach ($container->findTaggedServiceIds('assetic.templating.twig') as $id => $attr) {
                $container->remove($id);
            }
        }

        if (in_array('php', $engines)) {
            // $am->addMethodCall('addCacheFile', array('%kernel.cache_dir%/assetic_php_assets.php'));
        } else {
            foreach ($container->findTaggedServiceIds('assetic.templating.php') as $id => $attr) {
                $container->remove($id);
            }
        }
    }
}

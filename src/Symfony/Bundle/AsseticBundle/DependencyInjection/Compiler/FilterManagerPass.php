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
 * Adds services tagged as filters to the filter manager.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class FilterManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('assetic.filter_manager')) {
            return;
        }

        $mapping = array();
        foreach ($container->findTaggedServiceIds('assetic.filter') as $id => $attributes) {
            foreach ($attributes as $attr) {
                if (isset($attr['alias'])) {
                    $mapping[$attr['alias']] = $id;
                }
            }
        }

        $container
            ->getDefinition('assetic.filter_manager')
            ->replaceArgument(1, $mapping);
    }
}

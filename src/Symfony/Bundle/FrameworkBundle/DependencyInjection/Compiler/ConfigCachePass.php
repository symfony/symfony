<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds services tagged config_cache.resource_checker to the config_cache_factory service, ordering them by priority.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 * @author Benjamin Klotz <bk@webfactory.de>
 */
class ConfigCachePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $resourceCheckers = array();

        foreach ($container->findTaggedServiceIds('config_cache.resource_checker') as $id => $tags) {
            $priority = isset($tags[0]['priority']) ? $tags[0]['priority'] : 0;
            $resourceCheckers[$priority][] = new Reference($id);
        }

        if (empty($resourceCheckers)) {
            return;
        }

        // sort by priority and flatten
        krsort($resourceCheckers);
        $resourceCheckers = call_user_func_array('array_merge', $resourceCheckers);

        $container->getDefinition('config_cache_factory')->replaceArgument(0, $resourceCheckers);
    }
}

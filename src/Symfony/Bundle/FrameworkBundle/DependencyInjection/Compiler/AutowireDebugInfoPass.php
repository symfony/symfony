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
 * Looks for & processes debug.autowiring_info_provider tags.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class AutowireDebugInfoPass implements CompilerPassInterface
{
    const AUTOWIRING_INFO_PROVIDER_TAG = 'debug.autowiring_info_provider';

    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('debug.autowiring_info_manager')) {
            return;
        }

        $definition = $container->getDefinition('debug.autowiring_info_manager');

        $references = array();
        foreach ($container->findTaggedServiceIds(self::AUTOWIRING_INFO_PROVIDER_TAG, true) as $serviceId => $attributes) {
            $references[] = new Reference($serviceId);
        }

        $definition->replaceArgument(0, $references);
    }
}

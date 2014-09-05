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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class ConfigResourceRefresherPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('config.resource.chain_refresher')) {
            return;
        }

        $definition = $container->getDefinition('config.resource.chain_refresher');

        foreach ($container->findTaggedServiceIds('config.resource_refresher') as $id => $attributes) {
            $definition->addMethodCall('addRefresher', array(new Reference($id)));
        }
    }
}

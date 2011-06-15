<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * RegisterPluginsPass registers Swiftmailer plugins.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RegisterPluginsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('swiftmailer.mailer')) {
            return;
        }

        $definition = $container->findDefinition('swiftmailer.transport');
        $plugins = array();
        foreach ($container->findTaggedServiceIds('swiftmailer.plugin') as $id => $args) {
            $priority = isset($args[0]['priority']) ? $args[0]['priority'] : 0;
            $plugins[$priority][] = new Reference($id);
        }

        if ($plugins) {
            krsort($plugins);
            $plugins = call_user_func_array('array_merge', $plugins);
            foreach ($plugins as $plugin) {
                $definition->addMethodCall('registerPlugin', array($plugin));
            }
        }
    }
}

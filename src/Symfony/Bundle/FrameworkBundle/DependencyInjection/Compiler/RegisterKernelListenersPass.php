<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class RegisterKernelListenersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('event_dispatcher')) {
            return;
        }

        $listeners = array();
        foreach ($container->findTaggedServiceIds('kernel.listener') as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;

            if (!isset($listeners[$priority])) {
                $listeners[$priority] = array();
            }

            $listeners[$priority][] = new Reference($id);
        }

        $container
            ->getDefinition('event_dispatcher')
            ->addMethodCall('registerKernelListeners', array($listeners))
        ;
    }
}
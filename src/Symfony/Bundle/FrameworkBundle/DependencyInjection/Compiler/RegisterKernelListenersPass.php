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
        foreach ($container->findTaggedServiceIds('kernel.listener') as $id => $events) {
            foreach ($events as $event) {
                $priority = isset($event['priority']) ? $event['priority'] : 0;
                if (!isset($event['event'])) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "kernel.listener" tags.', $id));
                }
                if (!isset($event['method'])) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" must define the "method" attribute on "kernel.listener" tags.', $id));
                }

                if (!isset($listeners[$event['event']][$priority])) {
                    if (!isset($listeners[$event['event']])) {
                        $listeners[$event['event']] = array();
                    }
                    $listeners[$event['event']][$priority] = array();
                }

                $listeners[$event['event']][$priority][] = array($id, $event['method']);
            }
        }

        $container
            ->getDefinition('event_dispatcher')
            ->addMethodCall('registerKernelListeners', array($listeners))
        ;
    }
}

<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\DefinitionDecorator;

class RegisterEventListenersAndSubscribersPass implements CompilerPassInterface
{
    private $container;
    private $connections;
    private $eventManagers;

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('doctrine')) {
            return;
        }

        $this->container = $container;
        $this->connections = $container->getParameter('doctrine.connections');
        $sortFunc = function($a, $b) {
            $a = isset($a['priority']) ? $a['priority'] : 0;
            $b = isset($b['priority']) ? $b['priority'] : 0;

            return $a > $b ? -1 : 1;
        };

        $subscribersPerCon = $this->groupByConnection($container->findTaggedServiceIds('doctrine.event_subscriber'));
        foreach ($subscribersPerCon as $con => $subscribers) {
            $em = $this->getEventManager($con);

            uasort($subscribers, $sortFunc);
            foreach ($subscribers as $id => $instance) {
                $em->addMethodCall('addEventSubscriber', array(new Reference($id)));
            }
        }

        $listenersPerCon = $this->groupByConnection($container->findTaggedServiceIds('doctrine.event_listener'), true);
        foreach ($listenersPerCon as $con => $listeners) {
            $em = $this->getEventManager($con);

            uasort($listeners, $sortFunc);
            foreach ($listeners as $id => $instance) {
                $em->addMethodCall('addEventListener', array(
                    array_unique($instance['event']),
                    new Reference($id),
                ));
            }
        }
    }

    private function groupByConnection(array $services, $isListener = false)
    {
        $grouped = array();
        foreach (array_keys($this->connections) as $con) {
            $grouped[$con] = array();
        }

        foreach ($services as $id => $instances) {
            foreach ($instances as $instance) {
                $cons = isset($instance['connection']) ? array($instance['connection']) : array_keys($this->connections);
                foreach ($cons as $con) {
                    if (!isset($grouped[$con])) {
                        throw new \RuntimeException(sprintf('The doctrine connection "%s" referenced in service "%s" does not exist. Available connections names: %s', $con, $id, implode(', ', array_keys($this->connections))));
                    }

                    if ($isListener) {
                        if (!isset($instance['event'])) {
                            throw new \InvalidArgumentException(sprintf('Doctrine event listener "%s" must specify the "event" attribute.', $id));
                        }
                        $instance['event'] = array($instance['event']);

                        if (isset($grouped[$con][$id])) {
                            $grouped[$con][$id]['event'] += $instance['event'];
                            continue;
                        }
                    }

                    $grouped[$con][$id] = $instance;
                }
            }
        }

        return $grouped;
    }

    private function getEventManager($name)
    {
        if (null === $this->eventManagers) {
            $this->eventManagers = array();
            foreach ($this->connections as $n => $id) {
                $arguments = $this->container->getDefinition($id)->getArguments();
                $this->eventManagers[$n] = $this->container->getDefinition((string) $arguments[2]);
            }
        }

        return $this->eventManagers[$name];
    }
}

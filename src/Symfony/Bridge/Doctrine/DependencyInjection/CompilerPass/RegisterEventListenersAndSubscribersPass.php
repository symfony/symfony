<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Registers event listeners and subscribers to the available doctrine connections.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class RegisterEventListenersAndSubscribersPass implements CompilerPassInterface
{
    private $connections;
    private $container;
    private $eventManagers;
    private $managerTemplate;
    private $tagPrefix;

    /**
     * Constructor.
     *
     * @param string $connections     Parameter ID for connections
     * @param string $managerTemplate sprintf() template for generating the event
     *                                manager's service ID for a connection name
     * @param string $tagPrefix       Tag prefix for listeners and subscribers
     */
    public function __construct($connections, $managerTemplate, $tagPrefix)
    {
        $this->connections = $connections;
        $this->managerTemplate = $managerTemplate;
        $this->tagPrefix = $tagPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter($this->connections)) {
            return;
        }

        $taggedSubscribers = $container->findTaggedServiceIds($this->tagPrefix.'.event_subscriber', true);
        $taggedListeners = $container->findTaggedServiceIds($this->tagPrefix.'.event_listener', true);

        if (empty($taggedSubscribers) && empty($taggedListeners)) {
            return;
        }

        $this->container = $container;
        $this->connections = $container->getParameter($this->connections);
        $sortFunc = function ($a, $b) {
            $a = isset($a['priority']) ? $a['priority'] : 0;
            $b = isset($b['priority']) ? $b['priority'] : 0;

            return $a > $b ? -1 : 1;
        };

        if (!empty($taggedSubscribers)) {
            $subscribersPerCon = $this->groupByConnection($taggedSubscribers);
            foreach ($subscribersPerCon as $con => $subscribers) {
                $em = $this->getEventManager($con);

                uasort($subscribers, $sortFunc);
                foreach ($subscribers as $id => $instance) {
                    $em->addMethodCall('addEventSubscriber', array(new Reference($id)));
                }
            }
        }

        if (!empty($taggedListeners)) {
            $listenersPerCon = $this->groupByConnection($taggedListeners, true);
            foreach ($listenersPerCon as $con => $listeners) {
                $em = $this->getEventManager($con);

                uasort($listeners, $sortFunc);
                foreach ($listeners as $id => $instance) {
                    $em->addMethodCall('addEventListener', array(
                        array_unique($instance['event']),
                        isset($instance['lazy']) && $instance['lazy'] ? $id : new Reference($id),
                    ));
                }
            }
        }
    }

    private function groupByConnection(array $services, $isListener = false)
    {
        $grouped = array();
        foreach ($allCons = array_keys($this->connections) as $con) {
            $grouped[$con] = array();
        }

        foreach ($services as $id => $instances) {
            foreach ($instances as $instance) {
                if ($isListener) {
                    if (!isset($instance['event'])) {
                        throw new InvalidArgumentException(sprintf('Doctrine event listener "%s" must specify the "event" attribute.', $id));
                    }
                    $instance['event'] = array($instance['event']);

                    if ($lazy = !empty($instance['lazy'])) {
                        $this->container->getDefinition($id)->setPublic(true);
                    }
                }

                $cons = isset($instance['connection']) ? array($instance['connection']) : $allCons;
                foreach ($cons as $con) {
                    if (!isset($grouped[$con])) {
                        throw new RuntimeException(sprintf('The Doctrine connection "%s" referenced in service "%s" does not exist. Available connections names: %s', $con, $id, implode(', ', array_keys($this->connections))));
                    }

                    if ($isListener && isset($grouped[$con][$id])) {
                        $grouped[$con][$id]['event'] = array_merge($grouped[$con][$id]['event'], $instance['event']);
                    } else {
                        $grouped[$con][$id] = $instance;
                    }
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
                $this->eventManagers[$n] = $this->container->getDefinition(sprintf($this->managerTemplate, $n));
            }
        }

        return $this->eventManagers[$name];
    }
}

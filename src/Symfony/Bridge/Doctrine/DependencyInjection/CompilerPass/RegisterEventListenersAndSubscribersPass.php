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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers event listeners and subscribers to the available doctrine connections.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Alexander <iam.asm89@gmail.com>
 * @author David Maicher <mail@dmaicher.de>
 */
class RegisterEventListenersAndSubscribersPass implements CompilerPassInterface
{
    private $connections;
    private $eventManagers;
    private $managerTemplate;
    private $tagPrefix;

    /**
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

        $this->connections = $container->getParameter($this->connections);
        $this->addTaggedSubscribers($container);
        $this->addTaggedListeners($container);
    }

    private function addTaggedSubscribers(ContainerBuilder $container)
    {
        $subscriberTag = $this->tagPrefix.'.event_subscriber';
        $taggedSubscribers = $this->findAndSortTags($subscriberTag, $container);

        foreach ($taggedSubscribers as $taggedSubscriber) {
            list($id, $tag) = $taggedSubscriber;
            $connections = isset($tag['connection']) ? array($tag['connection']) : array_keys($this->connections);
            foreach ($connections as $con) {
                if (!isset($this->connections[$con])) {
                    throw new RuntimeException(sprintf('The Doctrine connection "%s" referenced in service "%s" does not exist. Available connections names: %s', $con, $taggedSubscriber, implode(', ', array_keys($this->connections))));
                }

                $this->getEventManagerDef($container, $con)->addMethodCall('addEventSubscriber', array(new Reference($id)));
            }
        }
    }

    private function addTaggedListeners(ContainerBuilder $container)
    {
        $listenerTag = $this->tagPrefix.'.event_listener';
        $taggedListeners = $this->findAndSortTags($listenerTag, $container);

        foreach ($taggedListeners as $taggedListener) {
            list($id, $tag) = $taggedListener;
            $taggedListenerDef = $container->getDefinition($id);
            if (!isset($tag['event'])) {
                throw new InvalidArgumentException(sprintf('Doctrine event listener "%s" must specify the "event" attribute.', $id));
            }

            $connections = isset($tag['connection']) ? array($tag['connection']) : array_keys($this->connections);
            foreach ($connections as $con) {
                if (!isset($this->connections[$con])) {
                    throw new RuntimeException(sprintf('The Doctrine connection "%s" referenced in service "%s" does not exist. Available connections names: %s', $con, $id, implode(', ', array_keys($this->connections))));
                }

                if ($lazy = !empty($tag['lazy'])) {
                    $taggedListenerDef->setPublic(true);
                }

                // we add one call per event per service so we have the correct order
                $this->getEventManagerDef($container, $con)->addMethodCall('addEventListener', array(array($tag['event']), $lazy ? $id : new Reference($id)));
            }
        }
    }

    private function getEventManagerDef(ContainerBuilder $container, $name)
    {
        if (!isset($this->eventManagers[$name])) {
            $this->eventManagers[$name] = $container->getDefinition(sprintf($this->managerTemplate, $name));
        }

        return $this->eventManagers[$name];
    }

    /**
     * Finds and orders all service tags with the given name by their priority.
     *
     * The order of additions must be respected for services having the same priority,
     * and knowing that the \SplPriorityQueue class does not respect the FIFO method,
     * we should not use this class.
     *
     * @see https://bugs.php.net/bug.php?id=53710
     * @see https://bugs.php.net/bug.php?id=60926
     *
     * @param string           $tagName
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function findAndSortTags($tagName, ContainerBuilder $container)
    {
        $sortedTags = array();

        foreach ($container->findTaggedServiceIds($tagName, true) as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $priority = isset($attributes['priority']) ? $attributes['priority'] : 0;
                $sortedTags[$priority][] = array($serviceId, $attributes);
            }
        }

        if ($sortedTags) {
            krsort($sortedTags);
            $sortedTags = call_user_func_array('array_merge', $sortedTags);
        }

        return $sortedTags;
    }
}

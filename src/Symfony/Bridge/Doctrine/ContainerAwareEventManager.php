<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine;

use Doctrine\Common\EventArgs;
use Doctrine\Common\EventManager;
use Psr\Container\ContainerInterface;

/**
 * Allows lazy loading of listener services.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ContainerAwareEventManager extends EventManager
{
    /**
     * Map of registered listeners.
     *
     * <event> => <listeners>
     */
    private $listeners = [];
    private $initialized = [];
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatchEvent($eventName, EventArgs $eventArgs = null)
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        $eventArgs = null === $eventArgs ? EventArgs::getEmptyInstance() : $eventArgs;

        if (!isset($this->initialized[$eventName])) {
            $this->initializeListeners($eventName);
        }

        foreach ($this->listeners[$eventName] as $hash => $listener) {
            $listener->$eventName($eventArgs);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners($event = null)
    {
        if (null !== $event) {
            if (!isset($this->initialized[$event])) {
                $this->initializeListeners($event);
            }

            return $this->listeners[$event];
        }

        foreach ($this->listeners as $event => $listeners) {
            if (!isset($this->initialized[$event])) {
                $this->initializeListeners($event);
            }
        }

        return $this->listeners;
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners($event)
    {
        return isset($this->listeners[$event]) && $this->listeners[$event];
    }

    /**
     * {@inheritdoc}
     */
    public function addEventListener($events, $listener)
    {
        if (\is_string($listener)) {
            $hash = '_service_'.$listener;
        } else {
            // Picks the hash code related to that listener
            $hash = spl_object_hash($listener);
        }

        foreach ((array) $events as $event) {
            // Overrides listener if a previous one was associated already
            // Prevents duplicate listeners on same event (same instance only)
            $this->listeners[$event][$hash] = $listener;

            if (\is_string($listener)) {
                unset($this->initialized[$event]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeEventListener($events, $listener)
    {
        if (\is_string($listener)) {
            $hash = '_service_'.$listener;
        } else {
            // Picks the hash code related to that listener
            $hash = spl_object_hash($listener);
        }

        foreach ((array) $events as $event) {
            // Check if actually have this listener associated
            if (isset($this->listeners[$event][$hash])) {
                unset($this->listeners[$event][$hash]);
            }
        }
    }

    /**
     * @param string $eventName
     */
    private function initializeListeners($eventName)
    {
        foreach ($this->listeners[$eventName] as $hash => $listener) {
            if (\is_string($listener)) {
                $this->listeners[$eventName][$hash] = $this->container->get($listener);
            }
        }
        $this->initialized[$eventName] = true;
    }
}

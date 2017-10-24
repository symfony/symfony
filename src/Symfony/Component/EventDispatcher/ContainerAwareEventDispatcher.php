<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lazily loads listeners and subscribers from the dependency injection
 * container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 *
 * @deprecated since 3.3, to be removed in 4.0. Use EventDispatcher with closure factories instead.
 */
class ContainerAwareEventDispatcher extends EventDispatcher
{
    private $container;

    /**
     * The service IDs of the event listeners and subscribers.
     */
    private $listenerIds = array();

    /**
     * The services registered as listeners.
     */
    private $listeners = array();

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $class = get_class($this);
        if ($this instanceof \PHPUnit_Framework_MockObject_MockObject || $this instanceof \Prophecy\Doubler\DoubleInterface) {
            $class = get_parent_class($class);
        }
        if (__CLASS__ !== $class) {
            @trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use EventDispatcher with closure factories instead.', __CLASS__), E_USER_DEPRECATED);
        }
    }

    /**
     * Adds a service as event listener.
     *
     * @param string $eventName Event for which the listener is added
     * @param array  $callback  The service ID of the listener service & the method
     *                          name that has to be called
     * @param int    $priority  The higher this value, the earlier an event listener
     *                          will be triggered in the chain.
     *                          Defaults to 0.
     *
     * @throws \InvalidArgumentException
     */
    public function addListenerService($eventName, $callback, $priority = 0)
    {
        @trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use EventDispatcher with closure factories instead.', __CLASS__), E_USER_DEPRECATED);

        if (!is_array($callback) || 2 !== count($callback)) {
            throw new \InvalidArgumentException('Expected an array("service", "method") argument');
        }

        $this->listenerIds[$eventName][] = array($callback[0], $callback[1], $priority);
    }

    public function removeListener($eventName, $listener)
    {
        $this->lazyLoad($eventName);

        if (isset($this->listenerIds[$eventName])) {
            foreach ($this->listenerIds[$eventName] as $i => list($serviceId, $method, $priority)) {
                $key = $serviceId.'.'.$method;
                if (isset($this->listeners[$eventName][$key]) && $listener === array($this->listeners[$eventName][$key], $method)) {
                    unset($this->listeners[$eventName][$key]);
                    if (empty($this->listeners[$eventName])) {
                        unset($this->listeners[$eventName]);
                    }
                    unset($this->listenerIds[$eventName][$i]);
                    if (empty($this->listenerIds[$eventName])) {
                        unset($this->listenerIds[$eventName]);
                    }
                }
            }
        }

        parent::removeListener($eventName, $listener);
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners($eventName = null)
    {
        if (null === $eventName) {
            return $this->listenerIds || $this->listeners || parent::hasListeners();
        }

        if (isset($this->listenerIds[$eventName])) {
            return true;
        }

        return parent::hasListeners($eventName);
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners($eventName = null)
    {
        if (null === $eventName) {
            foreach ($this->listenerIds as $serviceEventName => $args) {
                $this->lazyLoad($serviceEventName);
            }
        } else {
            $this->lazyLoad($eventName);
        }

        return parent::getListeners($eventName);
    }

    /**
     * {@inheritdoc}
     */
    public function getListenerPriority($eventName, $listener)
    {
        $this->lazyLoad($eventName);

        return parent::getListenerPriority($eventName, $listener);
    }

    /**
     * Adds a service as event subscriber.
     *
     * @param string $serviceId The service ID of the subscriber service
     * @param string $class     The service's class name (which must implement EventSubscriberInterface)
     */
    public function addSubscriberService($serviceId, $class)
    {
        @trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use EventDispatcher with closure factories instead.', __CLASS__), E_USER_DEPRECATED);

        foreach ($class::getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->listenerIds[$eventName][] = array($serviceId, $params, 0);
            } elseif (is_string($params[0])) {
                $this->listenerIds[$eventName][] = array($serviceId, $params[0], isset($params[1]) ? $params[1] : 0);
            } else {
                foreach ($params as $listener) {
                    $this->listenerIds[$eventName][] = array($serviceId, $listener[0], isset($listener[1]) ? $listener[1] : 0);
                }
            }
        }
    }

    public function getContainer()
    {
        @trigger_error('The '.__METHOD__.'() method is deprecated since version 3.3 as its class will be removed in 4.0. Inject the container or the services you need in your listeners/subscribers instead.', E_USER_DEPRECATED);

        return $this->container;
    }

    /**
     * Lazily loads listeners for this event from the dependency injection
     * container.
     *
     * @param string $eventName The name of the event to dispatch. The name of
     *                          the event is the name of the method that is
     *                          invoked on listeners.
     */
    protected function lazyLoad($eventName)
    {
        if (isset($this->listenerIds[$eventName])) {
            foreach ($this->listenerIds[$eventName] as list($serviceId, $method, $priority)) {
                $listener = $this->container->get($serviceId);

                $key = $serviceId.'.'.$method;
                if (!isset($this->listeners[$eventName][$key])) {
                    $this->addListener($eventName, array($listener, $method), $priority);
                } elseif ($this->listeners[$eventName][$key] !== $listener) {
                    parent::removeListener($eventName, array($this->listeners[$eventName][$key], $method));
                    $this->addListener($eventName, array($listener, $method), $priority);
                }

                $this->listeners[$eventName][$key] = $listener;
            }
        }
    }
}

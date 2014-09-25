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
use Symfony\Component\DependencyInjection\IntrospectableContainerInterface;

/**
 * Lazily loads listeners and subscribers from the dependency injection
 * container
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 */
class ContainerAwareEventDispatcher extends EventDispatcher
{
    /**
     * The container from where services are loaded
     * @var ContainerInterface
     */
    private $container;

    /**
     * A list of proxy closures indexed by eventName, serviceId, method.
     * @var ContainerInterface
     */
    private $proxies;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Adds a service as event listener
     *
     * @param string $eventName Event for which the listener is added
     * @param array  $callback  The service ID of the listener service & the method
     *                            name that has to be called
     * @param int     $priority The higher this value, the earlier an event listener
     *                            will be triggered in the chain.
     *                            Defaults to 0.
     *
     * @throws \InvalidArgumentException
     */
    public function addListenerService($eventName, $callback, $priority = 0)
    {
        if (!is_array($callback) || 2 !== count($callback)) {
            throw new \InvalidArgumentException('Expected an array("service", "method") argument');
        }

        $container = $this->container;
        list($serviceId, $method) = $callback;

        if (isset($this->proxies[$eventName][$serviceId][$method])) {
            $proxy = $this->proxies[$eventName][$serviceId][$method];
            unset($this->proxies[$eventName][$serviceId][$method]);
            parent::removeListener($eventName, $proxy);
        }

        $proxy = function ($event, $eventName, $dispatcher) use ($container, $serviceId, $method) {
            call_user_func(array($container->get($serviceId), $method), $event, $eventName, $dispatcher);
        };

        $this->proxies[$eventName][$serviceId][$method] = $proxy;
        parent::addListener($eventName, $proxy, $priority);
    }

    /**
     * @see EventDispatcherInterface::removeListener
     */
    public function removeListener($eventName, $listener)
    {
        $introspect = ($this->container instanceof IntrospectableContainerInterface);
        if (isset($this->proxies[$eventName])) {
            foreach ($this->proxies[$eventName] as $serviceId => $methods) {
                foreach ($methods as $method => $proxy) {
                    if (!$introspect || $this->container->initialized($serviceId)) {
                        if ($listener === array($this->container->get($serviceId), $method)) {
                            unset($this->proxies[$eventName][$serviceId][$method]);
                            parent::removeListener($eventName, $proxy);

                            return;
                        }
                    }
                }
            }
        }

        parent::removeListener($eventName, $listener);
    }

    /**
     * Adds a service as event subscriber
     *
     * @param string $serviceId The service ID of the subscriber service
     * @param string $class     The service's class name (which must implement EventSubscriberInterface)
     */
    public function addSubscriberService($serviceId, $class)
    {
        foreach ($class::getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->addListenerService($eventName, array($serviceId, $params), 0);
            } elseif (is_string($params[0])) {
                $this->addListenerService($eventName, array($serviceId, $params[0]), isset($params[1]) ? $params[1] : 0);
            } else {
                foreach ($params as $listener) {
                    $this->addListenerService($eventName, array($serviceId, $listener[0]), isset($listener[1]) ? $listener[1] : 0);
                }
            }
        }
    }

    public function getContainer()
    {
        return $this->container;
    }
}

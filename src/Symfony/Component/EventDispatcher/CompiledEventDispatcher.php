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

use Symfony\Component\DependencyInjection\IntrospectableContainerInterface;

/**
 * A performance optimized container aware event dispatcher.
 *
 * This version of the event dispatcher contains the following optimizations
 * in comparison to the Symfony event dispatcher component:
 *
 * <dl>
 *   <dt>Faster instantiation of the event dispatcher service</dt>
 *   <dd>
 *     Instead of calling <code>addSubscriberService</code> once for each
 *     subscriber, a precompiled array of listener definitions is passed
 *     directly to the constructor. This is faster by an order of magnitude.
 *     The listeners are collected and prepared using a compiler
 *     pass.
 *   </dd>
 *   <dt>Lazy instantiation of listeners</dt>
 *   <dd>
 *     Services are only retrieved from the container just before invocation.
 *     Especially when dispatching the KernelEvents::REQUEST event, this leads
 *     to a more timely invocation of the first listener. Overall dispatch
 *     runtime is not affected by this change though.
 *   </dd>
 * </dl>
 */
class CompiledEventDispatcher implements EventDispatcherInterface
{
    /**
     * The service container.
     *
     * @var \Symfony\Component\DependencyInjection\IntrospectableContainerInterface
     */
    private $container;

    /**
     * Listener definitions.
     *
     * A nested array of listener definitions keyed by event name and priority.
     * A listener definition is an associative array with one of the following key
     * value pairs:
     * - callable: A callable listener
     * - service: An array of the form array(service id, method)
     *
     * A service entry will be resolved to a callable only just before its
     * invocation.
     *
     * @var array
     */
    private $listeners;

    /**
     * Whether listeners need to be sorted prior to dispatch, keyed by event name.
     *
     * @var array
     */
    private $unsorted = array();

    /**
     * Constructs a container aware event dispatcher.
     *
     * @param \Symfony\Component\EventDispatcher\IntrospectableContainerInterface $container
     *   The service container.
     * @param array $listeners
     *   A nested array of listener definitions keyed by event name and priority.
     *   The array is expected to be ordered by priority. A listener definition is
     *   an associative array with one of the following key value pairs:
     *   - callable: A callable listener
     *   - service: An array of the form array(service id, method)
     *   A service entry will be resolved to a callable only just before its
     *   invocation.
     */
    public function __construct(IntrospectableContainerInterface $container, array $listeners = array())
    {
        $this->container = $container;
        $this->listeners = $listeners;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($eventName, Event $event = null)
    {
        if ($event === NULL) {
            $event = new Event();
        }

        $event->setDispatcher($this);
        $event->setName($eventName);

        if (isset($this->listeners[$eventName])) {
            // Sort listeners if necessary.
            if (isset($this->unsorted[$eventName])) {
                krsort($this->listeners[$eventName]);
                unset($this->unsorted[$eventName]);
            }

            // Invoke listeners and resolve callables if necessary.
            foreach ($this->listeners[$eventName] as &$definitions) {
                foreach ($definitions as $key => &$definition) {
                    if (!isset($definition['callable'])) {
                        $definition['callable'] = array($this->container->get($definition['service'][0]), $definition['service'][1]);
                    }

                    call_user_func($definition['callable'], $event, $eventName, $this);
                    if ($event->isPropagationStopped()) {
                        return $event;
                    }
                }
            }
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners($eventName = null)
    {
        $result = array();

        if ($eventName === NULL) {
            // If event name was omitted, collect all listeners of all events.
            foreach (array_keys($this->listeners) as $eventName) {
                $listeners = $this->getListeners($eventName);
                if (!empty($listeners)) {
                    $result[$eventName] = $listeners;
                }
            }
        } elseif (isset($this->listeners[$eventName])) {
            // Sort listeners if necessary.
            if (isset($this->unsorted[$eventName])) {
                krsort($this->listeners[$eventName]);
                unset($this->unsorted[$eventName]);
            }

            // Collect listeners and resolve callables if necessary.
            foreach ($this->listeners[$eventName] as &$definitions) {
                foreach ($definitions as $key => &$definition) {
                    if (!isset($definition['callable'])) {
                        $definition['callable'] = array($this->container->get($definition['service'][0]), $definition['service'][1]);
                    }

                    $result[] = $definition['callable'];
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners($eventName = null)
    {
        return (bool) count($this->getListeners($eventName));
    }

    /**
     * {@inheritdoc}
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        $this->listeners[$eventName][$priority][] = array('callable' => $listener);
        $this->unsorted[$eventName] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function removeListener($eventName, $listener)
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $priority => $definitions) {
            foreach ($definitions as $key => $definition) {
                if (!isset($definition['callable'])) {
                    if (!$this->container->initialized($definition['service'][0])) {
                        continue;
                    }
                    $definition['callable'] = array($this->container->get($definition['service'][0]), $definition['service'][1]);
                }

                if ($definition['callable'] === $listener) {
                    unset($this->listeners[$eventName][$priority][$key]);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->addListener($eventName, array($subscriber, $params));
            } elseif (is_string($params[0])) {
                $this->addListener($eventName, array($subscriber, $params[0]), isset($params[1]) ? $params[1] : 0);
            } else {
                foreach ($params as $listener) {
                    $this->addListener($eventName, array($subscriber, $listener[0]), isset($listener[1]) ? $listener[1] : 0);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_array($params) && is_array($params[0])) {
                foreach ($params as $listener) {
                    $this->removeListener($eventName, array($subscriber, $listener[0]));
                }
            } else {
                $this->removeListener($eventName, array($subscriber, is_string($params) ? $params : $params[0]));
            }
        }
    }
}

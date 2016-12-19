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

/**
 * Allows lazy loading of listeners.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class LazyEventManager extends EventManager
{
    /**
     * Map of registered listeners.
     *
     * <event> => <listeners>
     *
     * @var array
     */
    private $listeners = array();
    private $initialized = array();

    /**
     * The resolver used for lazy loading.
     *
     * @var callable
     */
    private $listenerResolver;

    /**
     * Constructor.
     *
     * @param callable $listenerResolver The resolver receives a string identifier of the listener
     *                                   and should return an instance of EventSubscriber.
     */
    public function __construct(callable $listenerResolver)
    {
        $this->listenerResolver = $listenerResolver;
    }

    /**
     * Dispatches an event to all registered listeners.
     *
     * @param string    $eventName The name of the event to dispatch. The name of the event is
     *                             the name of the method that is invoked on listeners.
     * @param EventArgs $eventArgs The event arguments to pass to the event handlers/listeners.
     *                             If not supplied, the single empty EventArgs instance is used.
     *
     * @return bool
     */
    public function dispatchEvent($eventName, EventArgs $eventArgs = null)
    {
        if (isset($this->listeners[$eventName])) {
            $eventArgs = null === $eventArgs ? EventArgs::getEmptyInstance() : $eventArgs;

            $initialized = isset($this->initialized[$eventName]);

            foreach ($this->listeners[$eventName] as $hash => $listener) {
                if (!$initialized && is_string($listener)) {
                    $this->listeners[$eventName][$hash] = $listener = call_user_func($this->listenerResolver, $listener);
                }

                $listener->$eventName($eventArgs);
            }
            $this->initialized[$eventName] = true;
        }
    }

    /**
     * Gets the listeners of a specific event or all listeners.
     *
     * @param string $event The name of the event
     *
     * @return array The event listeners for the specified event, or all event listeners
     */
    public function getListeners($event = null)
    {
        return $event ? $this->listeners[$event] : $this->listeners;
    }

    /**
     * Checks whether an event has any registered listeners.
     *
     * @param string $event
     *
     * @return bool TRUE if the specified event has any listeners, FALSE otherwise
     */
    public function hasListeners($event)
    {
        return isset($this->listeners[$event]) && $this->listeners[$event];
    }

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string|array  $events   The event(s) to listen on
     * @param object|string $listener The listener object
     *
     * @throws \RuntimeException
     */
    public function addEventListener($events, $listener)
    {
        if (is_string($listener)) {
            if ($this->initialized) {
                throw new \RuntimeException('Adding lazy-loading listeners after construction is not supported.');
            }

            $hash = '_service_'.$listener;
        } else {
            // Picks the hash code related to that listener
            $hash = spl_object_hash($listener);
        }

        foreach ((array) $events as $event) {
            // Overrides listener if a previous one was associated already
            // Prevents duplicate listeners on same event (same instance only)
            $this->listeners[$event][$hash] = $listener;
        }
    }

    /**
     * Removes an event listener from the specified events.
     *
     * @param string|array  $events
     * @param object|string $listener
     */
    public function removeEventListener($events, $listener)
    {
        if (is_string($listener)) {
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
}

<?php

namespace Symfony\Component\EventDispatcher;

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * EventDispatcher implements a dispatcher object.
 *
 * @see http://developer.apple.com/documentation/Cocoa/Conceptual/Notifications/index.html Apple's Cocoa framework
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher
{
    protected $listeners = array();

    /**
     * Connects a listener to a given event name.
     *
     * @param string|null $name      An event name or null to make the listener listen to all events
     * @param mixed       $listener  A PHP callable
     * @param integer     $priority  The priority (between -10 and 10 -- defaults to 0)
     */
    public function connect($name, $listener, $priority = 0)
    {
        if (!isset($this->listeners[$name][$priority])) {
            $this->listeners[$name][$priority] = array();
        }

        $this->listeners[$name][$priority][] = $listener;
    }

    /**
     * Disconnects a listener for a given event name.
     *
     * @param string|null     $name      An event name or null to disconnect a global listener
     * @param mixed|null      $listener  A PHP callable or null to disconnect all listeners
     *
     * @return mixed false if listener does not exist, null otherwise
     */
    public function disconnect($name=null, $listener = null)
    {
        if (!isset($this->listeners[$name])) {
            return false;
        }

        if (null === $listener) {
        	unset($this->listeners[$name]);
        	return;
        }

        foreach ($this->listeners[$name] as $priority => $callables) {
            foreach ($callables as $i => $callable) {
                if ($listener === $callable) {
                    unset($this->listeners[$name][$priority][$i]);
                }
            }
        }
    }

    /**
     * Notifies all listeners of a given event.
     *
     * @param Event $event A Event instance
     *
     * @return Event The Event instance
     */
    public function notify(Event $event)
    {
        foreach ($this->getListeners($event->getName(), true) as $listener) {
            call_user_func($listener, $event);
        }

        return $event;
    }

    /**
     * Notifies all listeners of a given event until one returns a non null value.
     *
     * @param  Event $event A Event instance
     *
     * @return Event The Event instance
     */
    public function notifyUntil(Event $event)
    {
        foreach ($this->getListeners($event->getName(), true) as $listener) {
            if (call_user_func($listener, $event)) {
                $event->setProcessed(true);
                break;
            }
        }

        return $event;
    }

    /**
     * Filters a value by calling all listeners of a given event.
     *
     * @param  Event  $event   A Event instance
     * @param  mixed    $value   The value to be filtered
     *
     * @return Event The Event instance
     */
    public function filter(Event $event, $value)
    {
        foreach ($this->getListeners($event->getName(), true) as $listener) {
            $value = call_user_func($listener, $event, $value);
        }

        $event->setReturnValue($value);

        return $event;
    }

    /**
     * Returns true if the given event name has some listeners.
     *
     * @param  string   $name             The event name
     * @param  boolean  $includeGlobals   Flag whether the global listeners 
     *                                    (name=null)  should be included
     *
     * @return Boolean true if some listeners are connected, false otherwise
     */
    public function hasListeners($name, $includeGlobals=false)
    {
        return (Boolean) count($this->getListeners($name, $includeGlobals));
    }

    /**
     * Returns all listeners associated with a given event name.
     *
     * @param  string   $name             The event name
     * @param  boolean  $includeGlobals   Flag whether the global listeners 
     *                                    (name=null)  should be included
     * @return array  An array of listeners
     */
    public function getListeners($name, $includeGlobals=false)
    {
        if (!isset($this->listeners[$name])) {
            return array();
        }
        
        $listeners = array();
        $all = $this->listeners[$name];
        
        // add global listeners if required and existing, but only if the
        // it's not global collection which is requested
        if (isset($this->listeners[null]) && $includeGlobals && $name != null) {
			foreach ($this->listeners[null] as $prio => $globalListeners) {
				if (isset($all[$prio])) {
					$all[$prio] = array_merge($all[$prio], $globalListeners);
				} else {
					$all[$prio] = $globalListeners;
				}
			}
        }
        
        // sort with respect to priority and flatten array
        ksort($all);
        foreach ($all as $l) {
            $listeners = array_merge($listeners, $l);
        }

        return $listeners;
    }
}

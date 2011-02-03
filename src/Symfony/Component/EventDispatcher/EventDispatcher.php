<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * EventDispatcher implements a dispatcher object.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher implements EventDispatcherInterface
{
    protected $listeners = array();

    /**
     * Connects a listener to a given event name.
     *
     * Listeners with a higher priority are executed first.
     *
     * @param string  $name      An event name
     * @param mixed   $listener  A PHP callable
     * @param integer $priority  The priority (between -10 and 10 -- defaults to 0)
     */
    public function connect($name, $listener, $priority = 0)
    {
        if (!isset($this->listeners[$name][$priority])) {
            if (!isset($this->listeners[$name])) {
                $this->listeners[$name] = array();
            }
            $this->listeners[$name][$priority] = array();
        }

        $this->listeners[$name][$priority][] = $listener;
    }

    /**
     * Disconnects one, or all listeners for the given event name.
     *
     * @param string     $name     An event name
     * @param mixed|null $listener The listener to remove, or null to remove all
     *
     * @return void
     */
    public function disconnect($name, $listener = null)
    {
        if (!isset($this->listeners[$name])) {
            return;
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
     * @param EventInterface $event An EventInterface instance
     */
    public function notify(EventInterface $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            call_user_func($listener, $event);
        }
    }

    /**
     * Notifies all listeners of a given event until one processes the event.
     *
     * @param  EventInterface $event An EventInterface instance
     *
     * @return mixed The returned value of the listener that processed the event
     */
    public function notifyUntil(EventInterface $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            $ret = call_user_func($listener, $event);
            if ($event->isProcessed()) {
                return $ret;
            }
        }
    }

    /**
     * Filters a value by calling all listeners of a given event.
     *
     * @param  EventInterface $event An EventInterface instance
     * @param  mixed          $value The value to be filtered
     *
     * @return mixed The filtered value
     */
    public function filter(EventInterface $event, $value)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            $value = call_user_func($listener, $event, $value);
        }

        return $value;
    }

    /**
     * Returns true if the given event name has some listeners.
     *
     * @param  string $name The event name
     *
     * @return Boolean true if some listeners are connected, false otherwise
     */
    public function hasListeners($name)
    {
        return (Boolean) count($this->getListeners($name));
    }

    /**
     * Returns all listeners associated with a given event name.
     *
     * @param  string $name The event name
     *
     * @return array  An array of listeners
     */
    public function getListeners($name)
    {
        if (!isset($this->listeners[$name])) {
            return array();
        }

        krsort($this->listeners[$name]);

        return call_user_func_array('array_merge', $this->listeners[$name]);
    }
}

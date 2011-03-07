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

/**
 * EventDispatcher implements a dispatcher object.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EventDispatcher implements EventDispatcherInterface
{
    protected $listeners = array();

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function filter(EventInterface $event, $value)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            $value = call_user_func($listener, $event, $value);
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function hasListeners($name)
    {
        return (Boolean) count($this->getListeners($name));
    }

    /**
     * {@inheritDoc}
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

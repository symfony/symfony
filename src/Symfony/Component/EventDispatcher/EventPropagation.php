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
 * EventPropagation tracks how Events are dispatched to listeners.
 *
 * @author  Matthias Pigulla <mp@webfactory.de>
 */
class EventPropagation
{
    /**
     * @var Boolean Whether no further event listeners should be triggered
     */
    private $stopped = false;

    /**
     * @var EventDispatcherInterface[] The stack of EventDispatchers involved in this propagation.
     */
    private $dispatcherStack = array();

    /**
     * @var string This event's name
     */
    private $name;

    /**
     * @var object The event object being propagated
     */
    private $event;

    /**
     * Constructor.
     *
     * @param string $name  The name used for propagating the event.
     * @param object $event The event object being propagated.
     */
    public function __construct($name, $event)
    {
        $this->name = $name;
        $this->event = $event;
    }

    /**
     * Returns whether further event listeners should be triggered.
     *
     * @see EventPropagation::stopPropagation
     * @return Boolean Whether propagation was already stopped for this event.
     */
    public function isStopped()
    {
        return $this->stopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stop()
    {
        $this->stopped = true;
    }

    /**
     * Tracks a new EventDispatcher involved in propagation of the event.
     *
     * @param EventDispatcherInterface $dispatcher
     */
    public function pushDispatcher(EventDispatcherInterface $dispatcher)
    {
        array_push($this->dispatcherStack, $dispatcher);
    }

    /**
     * Removes the current dispatcher from the stack.
     *
     * Has to be called
     * by EventDispatchers once they are done with the event.
     */
    public function popDispatcher()
    {
        array_pop($this->dispatcherStack);
    }

    /**
     * Returns the EventDispatcher that was initially used to dispatch
     * the event.
     *
     * @return EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return reset($this->dispatcherStack);
    }

    /**
     * Returns the EventDispatcher that is currently in charge of
     * propagating the event. This is the dispatcher that a listener
     * receiving the event was originally registered with.
     *
     * @return EventDispatcherInterface
     */
    public function getCurrentDispatcher()
    {
        return end($this->dispatcherStack);
    }


    /**
     * Gets the event's name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the event being propagated.
     *
     * @return object The event object.
     */
    public function getEvent()
    {
        return $this->event;
    }
}

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
 * Event is the base class for classes containing event data.
 *
 * This class contains no event data. It is used by events that do not pass
 * state information to an event handler when an event is raised.
 *
 * You can call the method stopPropagation() to abort the execution of
 * further listeners in your event listener.
 *
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Events objects are no longer requited to extend a base class
 *
 * @api
 */
class Event
{
    /**
     * @var EventPropagation The propagation class used to track this event
     */
    private $propagation;

    /**
     * Returns whether further event listeners should be triggered.
     *
     * @see Event::stopPropagation
     * @return Boolean Whether propagation was already stopped for this event.
     *
     * @api
     * @deprecated
     */
    public function isPropagationStopped()
    {
        trigger_error(
            'Calling isPropagationStopped() on the event object is deprecated. '.
            'Consult the EventPropagation class instead which is passed to the listener.',
            E_USER_DEPRECATED
        );

        return $this->propagation->isStopped();
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     *
     * @api
     * @deprecated
     */
    public function stopPropagation()
    {
        trigger_error(
            'Calling stopPropagation() on the event object is deprecated. '.
            'Call it on the EventPropagation class instead which is passed to the listener.',
            E_USER_DEPRECATED
        );

        $this->propagation->stop();
    }

    /**
     * Returns the EventDispatcher that dispatches this Event
     *
     * @return EventDispatcherInterface
     *
     * @api
     * @deprecated
     */
    public function getDispatcher()
    {
        trigger_error(
            'Calling getDispatcher() on the event object is deprecated. '.
            'The dispatchers are available on the EventPropagation class which is passed to the listener. '.
            'Your code will currently get the Dispatcher that was initially used to dispatch the event.',
            E_USER_DEPRECATED
        );

        return $this->propagation->getDispatcher();
    }

    /**
     * Gets the event's name.
     *
     * @return string
     *
     * @api
     * @deprecated
     */
    public function getName()
    {
        trigger_error(
            'Calling getName() on the event object is deprecated. '.
            'Call it on the EventPropagation class instead which is passed to the listener.',
            E_USER_DEPRECATED
        );

        return $this->propagation->getName();
    }

    /**
     * Injects the EventPropagation instance that tracks the propagation
     * of this event.
     *
     * @deprecated Injecting the EventPropagation is only necessary to ease migration to this class.

     * @param EventPropagation $propagation The propagation for this event.
     */
    public function setEventPropagation(EventPropagation $propagation)
    {
        $this->propagation = $propagation;
    }
}

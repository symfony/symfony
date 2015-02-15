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
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Event
{
    /**
     * @var bool Whether no further event listeners should be triggered
     */
    private $propagationStopped = false;

    /**
<<<<<<< HEAD
     * @var EventDispatcher Dispatcher that dispatched this event
     */
    private $dispatcher;

    /**
     * @var string This event's name
     */
    private $name;

    /**
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
     * Returns whether further event listeners should be triggered.
     *
     * @see Event::stopPropagation()
     *
     * @return bool Whether propagation was already stopped for this event.
     *
     * @api
     */
    public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     *
     * @api
     */
    public function stopPropagation()
    {
        $this->propagationStopped = true;
    }
<<<<<<< HEAD

    /**
     * Stores the EventDispatcher that dispatches this Event.
     *
     * @param EventDispatcherInterface $dispatcher
     *
     * @deprecated since version 2.4, to be removed in 3.0. The event dispatcher is passed to the listener call.
     *
     * @api
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Returns the EventDispatcher that dispatches this Event.
     *
     * @return EventDispatcherInterface
     *
     * @deprecated since version 2.4, to be removed in 3.0. The event dispatcher is passed to the listener call.
     *
     * @api
     */
    public function getDispatcher()
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.4 and will be removed in 3.0. The event dispatcher instance can be received in the listener call instead.', E_USER_DEPRECATED);

        return $this->dispatcher;
    }

    /**
     * Gets the event's name.
     *
     * @return string
     *
     * @deprecated since version 2.4, to be removed in 3.0. The event name is passed to the listener call.
     *
     * @api
     */
    public function getName()
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.4 and will be removed in 3.0. The event name can be received in the listener call instead.', E_USER_DEPRECATED);

        return $this->name;
    }

    /**
     * Sets the event's name property.
     *
     * @param string $name The event name.
     *
     * @deprecated since version 2.4, to be removed in 3.0. The event name is passed to the listener call.
     *
     * @api
     */
    public function setName($name)
    {
        $this->name = $name;
    }
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
}

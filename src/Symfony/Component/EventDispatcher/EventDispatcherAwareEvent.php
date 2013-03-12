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
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @api
 */
class EventDispatcherAwareEvent extends Event
{
    /**
     * @var EventDispatcher Dispatcher that dispatched this event
     */
    private $dispatcher;

    /**
     * Stores the EventDispatcher that dispatches this Event
     *
     * @param EventDispatcherInterface $dispatcher
     *
     * @api
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Returns the EventDispatcher that dispatches this Event
     *
     * @return EventDispatcherInterface
     *
     * @api
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }
}

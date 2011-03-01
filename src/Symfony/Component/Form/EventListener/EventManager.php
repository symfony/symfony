<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\EventListener;

class EventManager
{
    private $listeners = array();

    private $supportedEvents = array();

    public function __construct(array $supportedEvents)
    {
        $this->supportedEvents = $supportedEvents;
    }

    public function addEventListener(EventListenerInterface $listener)
    {
        foreach ((array)$listener->getSupportedEvents() as $event) {
            // TODO check whether the listener has the $event method

            if (!isset($this->listeners[$event])) {
                $this->listeners[$event] = array();
            }

            $this->listeners[$event][] = $listener;
        }
    }

    public function triggerEvent($event, $data = null)
    {
        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                $listener->$event($data);
            }
        }
    }
}
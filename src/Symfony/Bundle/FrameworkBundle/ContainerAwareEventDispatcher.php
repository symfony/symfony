<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;

/**
 * Lazily loads listeners and subscribers from the dependency injection
 * container
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ContainerAwareEventDispatcher extends EventDispatcher
{
    /**
     * The container from where services are loaded
     * @var ContainerInterface
     */
    private $container;

    /**
     * The service IDs of the event listeners and subscribers
     * @var array
     */
    private $listenerIds;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Adds a service as event listener
     *
     * @param string|array $events  One or more events for which the listener
     *                              is added
     * @param string $serviceId     The ID of the listener service
     * @param integer $priority     The higher this value, the earlier an event
     *                              listener will be triggered in the chain.
     *                              Defaults to 0.
     */
    public function addEventListenerService($events, $serviceId, $priority = 0)
    {
        if (!is_string($serviceId)) {
            throw new \InvalidArgumentException('Expected a string argument');
        }

        foreach ((array)$events as $event) {
            // Prevent duplicate entries
            $this->listenerIds[$event][$serviceId] = $priority;
        }
    }

    /**
     * Adds a service as event subscriber
     *
     * @param string $serviceId  The ID of the subscriber service
     * @param integer $priority  The higher this value, the earlier an event
     *                           listener will be triggered in the chain.
     *                           Defaults to 0.
     */
    public function addEventSuscriberService($serviceId, $priority = 0)
    {
        if (!is_string($serviceId)) {
            throw new \InvalidArgumentException('Expected a string argument');
        }

        // TODO get class name, call static method getSubscribedEvents()
        // and pass to addEventListenerService
    }

    /**
     * {@inheritDoc}
     *
     * Lazily loads listeners for this event from the dependency injection
     * container.
     */
    public function dispatchEvent($eventName, Event $event = null)
    {
        if (isset($this->listenerIds[$eventName])) {
            foreach ($this->listenerIds[$eventName] as $serviceId => $priority) {
                $this->addEventListener($eventName, $this->container->get($serviceId), $priority);
            }
        }

        parent::dispatchEvent($eventName, $event);
    }
}

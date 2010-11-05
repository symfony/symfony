<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\Event;

use Doctrine\Common\EventManager as BaseEventManager;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;

class EventManager extends BaseEventManager
{
    /**
     * Loads event listeners from the service container.
     *
     * A service can be marked as an event listener using the
     * "doctrine.odm.mongodb.event_listener" tag:
     *
     *     <service class="MyListener">
     *         <tag name="doctrine.odm.mongodb.event_listener" event="prePersist" />
     *         <tag name="doctrine.odm.mongodb.event_listener" event="preUpdate" />
     *     </service>
     *
     * @param TaggedContainerInterface $container The service container
     */
    public function loadTaggedEventListeners(TaggedContainerInterface $container)
    {
        foreach ($container->findTaggedServiceIds('doctrine.odm.mongodb.event_listener') as $id => $instances) {
            $events = array();
            foreach ($instances as $attributes) {
                if (isset($attributes['event'])) {
                    $events[] = $attributes['event'];
                }
            }

            if (0 < count($events)) {
                $this->addEventListener($events, $container->get($id));
            }
        }
    }

    /**
     * Loads event subscribers from the service container.
     *
     * A service can be marked as an event subscriber using the
     * "doctrine.odm.mongodb.event_subscriber" tag:
     *
     *     <service class="MySubscriber">
     *         <tag name="doctrine.odm.mongodb.event_subscriber" />
     *     </service>
     *
     * @param TaggedContainerInterface $container The service container
     */
    public function loadTaggedEventSubscribers(TaggedContainerInterface $container)
    {
        foreach ($container->findTaggedServiceIds('doctrine.odm.mongodb.event_subscriber') as $id => $instances) {
            $this->addEventSubscriber($container->get($id));
        }
    }
}

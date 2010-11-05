<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\Event;

use Doctrine\Common\EventManager as BaseEventManager;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;

class EventManager extends BaseEventManager
{
    /**
     * Loads event listeners from the service container.
     *
     * @param TaggedContainerInterface $container The service container
     * @param string $tagName The name of the tag to load
     */
    public function loadTaggedEventListeners(TaggedContainerInterface $container, $tagName)
    {
        foreach ($container->findTaggedServiceIds($tagName) as $id => $instances) {
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
     * @param TaggedContainerInterface $container The service container
     * @param string $tagName The name of the tag to load
     */
    public function loadTaggedEventSubscribers(TaggedContainerInterface $container, $tagName)
    {
        foreach ($container->findTaggedServiceIds($tagName) as $id => $instances) {
            $this->addEventSubscriber($container->get($id));
        }
    }
}

<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\Event;

use Doctrine\Common\EventManager as BaseEventManager;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;

/**
 * An event manager that can pull listeners and subscribers from the service container.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class EventManager extends BaseEventManager
{
    /**
     * Loads event listeners from the service container.
     *
     *     <service class="MyListener">
     *         <tag name="doctrine.odm.mongodb.event_listener" event="prePersist" />
     *         <tag name="doctrine.odm.mongodb.event_listener" event="preUpdate" />
     *     </service>
     *
     * @param TaggedContainerInterface $container The service container
     * @param string $tagName The name of the tag to load
     */
    public function loadTaggedEventListeners(TaggedContainerInterface $container, $tagName = 'doctrine.odm.mongodb.event_listener')
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
     *     <service class="MySubscriber">
     *         <tag name="doctrine.odm.mongodb.event_subscriber" />
     *     </service>
     *
     * @param TaggedContainerInterface $container The service container
     * @param string $tagName The name of the tag to load
     */
    public function loadTaggedEventSubscribers(TaggedContainerInterface $container, $tagName = 'doctrine.odm.mongodb.event_subscriber')
    {
        foreach ($container->findTaggedServiceIds($tagName) as $id => $instances) {
            $this->addEventSubscriber($container->get($id));
        }
    }
}

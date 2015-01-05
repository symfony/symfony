<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Compiler pass to register tagged services for a compiled event dispatcher.
 */
class CompiledRegisterListenersPass implements CompilerPassInterface
{
    /**
     * Service name of the event dispatcher in processed container.
     *
     * @var string
     */
    private $dispatcherService;

    /**
     * Tag name used for listeners.
     *
     * @var string
     */
    private $listenerTag;

    /**
     * Tag name used for subscribers.
     *
     * @var string
     */
    private $subscriberTag;

    /**
     * Constructor.
     *
     * @param string $dispatcherService Service name of the event dispatcher in processed container
     * @param string $listenerTag       Tag name used for listeners
     * @param string $subscriberTag     Tag name used for subscribers
     */
    public function __construct($dispatcherService = 'event_dispatcher', $listenerTag = 'kernel.event_listener', $subscriberTag = 'kernel.event_subscriber')
    {
        $this->dispatcherService = $dispatcherService;
        $this->listenerTag = $listenerTag;
        $this->subscriberTag = $subscriberTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->dispatcherService) && !$container->hasAlias($this->dispatcherService)) {
            return;
        }

        $definition = $container->findDefinition($this->dispatcherService);

        $listeners = array();

        foreach ($container->findTaggedServiceIds($this->listenerTag) as $id => $events) {
            $def = $container->getDefinition($id);
            if (!$def->isPublic()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must be public as event listeners are lazy-loaded.', $id));
            }

            if ($def->isAbstract()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must not be abstract as event listeners are lazy-loaded.', $id));
            }

            foreach ($events as $event) {
                $priority = isset($event['priority']) ? $event['priority'] : 0;

                if (!isset($event['event'])) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "%s" tags.', $id, $this->listenerTag));
                }

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace_callback(array(
                        '/(?<=\b)[a-z]/i',
                        '/[^a-z0-9]/i',
                    ), function ($matches) { return strtoupper($matches[0]); }, $event['event']);
                    $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);
                }

                $listeners[$event['event']][$priority][] = array('service' => array('id' => $id, 'method' => $event['method']));
            }
        }

        foreach ($container->findTaggedServiceIds($this->subscriberTag) as $id => $attributes) {
            $def = $container->getDefinition($id);
            if (!$def->isPublic()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must be public as event subscribers are lazy-loaded.', $id));
            }

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $def->getClass();

            $refClass = new \ReflectionClass($class);
            $interface = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';
            if (!$refClass->implementsInterface($interface)) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must implement interface "%s".', $id, $interface));
            }

            // Get all subscribed events.
            foreach ($class::getSubscribedEvents() as $eventName => $params) {
                if (is_string($params)) {
                    $priority = 0;
                    $listeners[$eventName][$priority][] = array('service' => array('id' => $id, 'method' => $params));
                } elseif (is_string($params[0])) {
                    $priority = isset($params[1]) ? $params[1] : 0;
                    $listeners[$eventName][$priority][] = array('service' => array('id' => $id, 'method' => $params[0]));
                } else {
                    foreach ($params as $listener) {
                        $priority = isset($listener[1]) ? $listener[1] : 0;
                        $listeners[$eventName][$priority][] = array('service' => array('id' => $id, 'method' => $listener[0]));
                    }
                }
            }
        }

        foreach (array_keys($listeners) as $eventName) {
            krsort($listeners[$eventName]);
        }

        $definition->addArgument($listeners);
    }
}

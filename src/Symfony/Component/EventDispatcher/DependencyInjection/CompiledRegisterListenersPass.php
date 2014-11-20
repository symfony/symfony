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
     * @var string
     */
    protected $dispatcherService;

    /**
     * @var string
     */
    protected $listenerTag;

    /**
     * @var string
     */
    protected $subscriberTag;

    /**
     * Constructor.
     *
     * @param string $dispatcherService Service name of the event dispatcher in processed container
     * @param string $listenerTag       Tag name used for listener
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

        // TODO: Also collect services tagged as listeners.

        $event_subscriber_info = array();
        foreach ($container->findTaggedServiceIds($this->subscriberTag) as $id => $attributes) {
            $def = $container->getDefinition($id);
            if (!$def->isPublic()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must be public as event listeners are lazy-loaded.', $id));
            }

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $def->getClass();

            $refClass = new \ReflectionClass($class);
            $interface = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';
            if (!$refClass->implementsInterface($interface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            // Get all subscribed events.
            foreach ($class::getSubscribedEvents() as $event_name => $params) {
                if (is_string($params)) {
                    $priority = 0;
                    $event_subscriber_info[$event_name][$priority][] = array('service' => array($id, $params));
                } elseif (is_string($params[0])) {
                    $priority = isset($params[1]) ? $params[1] : 0;
                    $event_subscriber_info[$event_name][$priority][] = array('service' => array($id, $params[0]));
                } else {
                    foreach ($params as $listener) {
                        $priority = isset($listener[1]) ? $listener[1] : 0;
                        $event_subscriber_info[$event_name][$priority][] = array('service' => array($id, $listener[0]));
                    }
                }
            }
        }

        foreach (array_keys($event_subscriber_info) as $event_name) {
            krsort($event_subscriber_info[$event_name]);
        }

        $definition->addArgument($event_subscriber_info);
    }
}

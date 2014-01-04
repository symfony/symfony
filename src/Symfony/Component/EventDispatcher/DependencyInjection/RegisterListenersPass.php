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
 * Compiler pass to register tagged services for an event dispatcher.
 */
class RegisterListenersPass implements CompilerPassInterface
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

    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds($this->listenerTag) as $id => $events) {
            $def = $container->getDefinition($id);
            if (!$def->isPublic()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must be public as event listeners are lazy-loaded.', $id));
            }

            if ($def->isAbstract()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must not be abstract as event listeners are lazy-loaded.', $id));
            }

            foreach ($events as $event) {
                $definition = $this->extractDispatcherDef($container, $this->listenerTag, $id, $event);

                /**
                 * @deprecated Using a dispatcher not available in the container is Deprecated in 2.5. Starting with 3.0, an exception will be thrown in this case.
                 */
                if (null === $definition) {
                    continue;
                }

                $priority = isset($event['priority']) ? $event['priority'] : 0;

                if (!isset($event['event'])) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "kernel.event_listener" tags.', $id));
                }

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace_callback(array(
                        '/(?<=\b)[a-z]/i',
                        '/[^a-z0-9]/i',
                    ), function ($matches) { return strtoupper($matches[0]); }, $event['event']);
                    $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);
                }

                $definition->addMethodCall('addListenerService', array($event['event'], array($id, $event['method']), $priority));
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
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            foreach ($attributes as $attribute) {
                $definition = $this->extractDispatcherDef($container, $this->subscriberTag, $id, $attribute);

                /**
                 * @deprecated Using a dispatcher not available in the container is Deprecated in 2.5. Starting with 3.0, an exception will be thrown in this case.
                 */
                if (null === $definition) {
                    continue;
                }

                $definition->addMethodCall('addSubscriberService', array($id, $class));
            }
        }
    }

    protected function extractDispatcherDef(ContainerBuilder $container, $tag, $id, $attributes)
    {
        $dispatcher = isset($attributes['dispatcher']) ? $attributes['dispatcher'] : $this->dispatcherService;

        if (!$container->hasDefinition($dispatcher) && !$container->hasAlias($dispatcher)) {
            /**
             * @deprecated Using a dispatcher not available in the container is Deprecated in 2.5. Starting with 3.0, an exception will be thrown in this case.
             */
            return null;
            // throw new \InvalidArgumentException(sprintf('The listener "%s" must specify an existing dispatcher service: "%s" not find in the container.', $id, $event['workflow']));
        }

        return $container->findDefinition($dispatcher);
    }

}

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

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventListenerInterface;

/**
 * Compiler pass to register tagged services for an event dispatcher.
 */
class RegisterListenersPass implements CompilerPassInterface
{
    protected $dispatcherService;
    protected $listenerTag;
    protected $subscriberTag;
    protected $eventAliasesParameter;

    private $hotPathEvents = [];
    private $hotPathTagName;

    public function __construct(string $dispatcherService = 'event_dispatcher', string $listenerTag = 'kernel.event_listener', string $subscriberTag = 'kernel.event_subscriber', string $eventAliasesParameter = 'event_dispatcher.event_aliases')
    {
        $this->dispatcherService = $dispatcherService;
        $this->listenerTag = $listenerTag;
        $this->subscriberTag = $subscriberTag;
        $this->eventAliasesParameter = $eventAliasesParameter;
    }

    public function setHotPathEvents(array $hotPathEvents, $tagName = 'container.hot_path')
    {
        $this->hotPathEvents = array_flip($hotPathEvents);
        $this->hotPathTagName = $tagName;

        return $this;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->dispatcherService) && !$container->hasAlias($this->dispatcherService)) {
            return;
        }

        if ($container->hasParameter($this->eventAliasesParameter)) {
            $aliases = $container->getParameter($this->eventAliasesParameter);
            $container->getParameterBag()->remove($this->eventAliasesParameter);
        } else {
            $aliases = [];
        }
        $definition = $container->findDefinition($this->dispatcherService);

        foreach ($container->findTaggedServiceIds($this->listenerTag, true) as $id => $events) {
            $reflection = null;
            foreach ($events as $event) {
                $priority = isset($event['priority']) ? $event['priority'] : 0;

                if (!isset($event['event'])) {
                    if (null === $reflection) {
                        $class = $container->getDefinition($id)->getClass();
                        if (!$reflection = $container->getReflectionClass($class)) {
                            throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                        }
                    }
                    if (!$reflection->implementsInterface(EventListenerInterface::class)) {
                        throw new InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "%s" tags or implements the "%s" interface.', $id, $this->listenerTag, EventListenerInterface::class));
                    }

                    $event['event'] = $this->guessListenedClass($reflection, $id);
                    $event['method'] = '__invoke';
                } else {
                    $event['event'] = $aliases[$event['event']] ?? $event['event'];
                }

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace_callback([
                        '/(?<=\b)[a-z]/i',
                        '/[^a-z0-9]/i',
                    ], function ($matches) { return strtoupper($matches[0]); }, (false === $p = strrpos($event['event'], '\\')) ? $event['event'] : substr($event['event'], $p + 1));
                    $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);

                    if (null === $reflection) {
                        $class = $container->getDefinition($id)->getClass();
                        if (!$reflection = $container->getReflectionClass($class)) {
                            throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                        }
                    }
                    if (!$reflection->hasMethod($event['method']) && $reflection->hasMethod('__invoke')) {
                        $event['method'] = '__invoke';
                    }
                }

                $definition->addMethodCall('addListener', [$event['event'], [new ServiceClosureArgument(new Reference($id)), $event['method']], $priority]);

                if (isset($this->hotPathEvents[$event['event']])) {
                    $container->getDefinition($id)->addTag($this->hotPathTagName);
                }
            }
        }

        $extractingDispatcher = new ExtractingEventDispatcher();

        foreach ($container->findTaggedServiceIds($this->subscriberTag, true) as $id => $attributes) {
            $def = $container->getDefinition($id);

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $def->getClass();

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$r->isSubclassOf(EventSubscriberInterface::class)) {
                throw new InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, EventSubscriberInterface::class));
            }
            $class = $r->name;

            ExtractingEventDispatcher::$aliases = $aliases;
            ExtractingEventDispatcher::$subscriber = $class;
            $extractingDispatcher->addSubscriber($extractingDispatcher);
            foreach ($extractingDispatcher->listeners as $args) {
                $args[1] = [new ServiceClosureArgument(new Reference($id)), $args[1]];
                $definition->addMethodCall('addListener', $args);

                if (isset($this->hotPathEvents[$args[0]])) {
                    $container->getDefinition($id)->addTag('container.hot_path');
                }
            }
            $extractingDispatcher->listeners = [];
            ExtractingEventDispatcher::$aliases = [];
        }
    }

    private function guessListenedClass(\ReflectionClass $handlerClass, string $serviceId): string
    {
        try {
            $method = $handlerClass->getMethod('__invoke');
        } catch (\ReflectionException $e) {
            throw new \InvalidArgumentException(sprintf('Invalid EventListener "%s": class "%s" must have an "__invoke()" method.', $serviceId, $handlerClass->getName()));
        }

        $parameters = $method->getParameters();
        if (1 > \count($parameters)) {
            throw new \InvalidArgumentException(sprintf('Invalid EventListener "%s": method "%s::__invoke()" must have, at least, one argument corresponding to the event it handles.', $serviceId, $handlerClass->getName()));
        }

        if (!$type = $parameters[0]->getType()) {
            throw new \InvalidArgumentException(sprintf('Invalid EventListener "%s": argument "$%s" of method "%s::__invoke()" must have a type-hint corresponding to the event class it handles.', $serviceId, $parameters[0]->getName(), $handlerClass->getName()));
        }

        if ($type->isBuiltin()) {
            throw new \InvalidArgumentException(sprintf('Invalid EventListener "%s": type-hint of argument "$%s" in method "%s::__invoke()" must be a class, "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $type));
        }

        return $parameters[0]->getType();
    }

    private function registerListenerCall(ContainerBuilder $container, Definition $definition, string $listerId, string $eventName, string $method, int $priority)
    {
        $callable = [new ServiceClosureArgument(new Reference($listerId)), $method];
        $definition->addMethodCall('addListener', [$eventName, $callable, $priority]);

        if (isset($this->hotPathEvents[$eventName])) {
            $container->getDefinition($listerId)->addTag($this->hotPathTagName);
        }
    }
}

/**
 * @internal
 */
class ExtractingEventDispatcher extends EventDispatcher implements EventSubscriberInterface
{
    public $listeners = [];

    public static $aliases = [];
    public static $subscriber;

    public function addListener($eventName, $listener, $priority = 0)
    {
        $this->listeners[] = [$eventName, $listener[1], $priority];
    }

    public static function getSubscribedEvents()
    {
        $events = [];

        foreach ([self::$subscriber, 'getSubscribedEvents']() as $eventName => $params) {
            $events[self::$aliases[$eventName] ?? $eventName] = $params;
        }

        return $events;
    }
}

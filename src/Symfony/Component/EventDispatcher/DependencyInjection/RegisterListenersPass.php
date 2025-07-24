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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Compiler pass to register tagged services for an event dispatcher.
 */
class RegisterListenersPass implements CompilerPassInterface
{
    private array $hotPathEvents = [];
    private array $noPreloadEvents = [];

    /**
     * @return $this
     */
    public function setHotPathEvents(array $hotPathEvents): static
    {
        $this->hotPathEvents = array_flip($hotPathEvents);

        return $this;
    }

    /**
     * @return $this
     */
    public function setNoPreloadEvents(array $noPreloadEvents): static
    {
        $this->noPreloadEvents = array_flip($noPreloadEvents);

        return $this;
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('event_dispatcher') && !$container->hasAlias('event_dispatcher')) {
            return;
        }

        $aliases = [];

        if ($container->hasParameter('event_dispatcher.event_aliases')) {
            $aliases = $container->getParameter('event_dispatcher.event_aliases');
        }

        $globalDispatcherDefinition = $container->findDefinition('event_dispatcher');

        foreach ($container->findTaggedServiceIds('kernel.event_listener', true) as $id => $events) {
            $noPreload = 0;

            foreach ($events as $event) {
                if (isset($event['event'])) {
                    trigger_deprecation('symfony/event-dispatcher', '7.4', 'The "event" attribute of the `kernel.event_listener` tag is deprecated and will be removed in Symfony 8.0. Use "events" attribute instead.');
                    if (isset($event['events'])) {
                        throw new InvalidArgumentException('Cannot use both "event" and "events" attributes of the `kernel.event_listener` tag.');
                    }
                    $event['events'] = $event['event'];
                }
                $priority = $event['priority'] ?? 0;

                if (!isset($event['events'])) {
                    if ($container->getDefinition($id)->hasTag('kernel.event_subscriber')) {
                        continue;
                    }

                    $event['method'] ??= '__invoke';
                    $event['events'] = $this->getEventFromTypeDeclaration($container, $id, $event['method']);
                }
                $event['events'] = array_map(fn (string $event) => $aliases[$event] ?? $event, (array) $event['events']);

                if (!isset($event['method'])) {
                    $class = $container->getDefinition($id)->getClass();
                    $reflectionClass = null !== $class ? $container->getReflectionClass($class, false) : null;
                    foreach ($event['events'] as $eventName) {
                        $methodName = 'on'.preg_replace_callback([
                            '/(?<=\b|_)[a-z]/i',
                            '/[^a-z0-9]/i',
                        ], fn ($matches) => strtoupper($matches[0]), $eventName);
                        $methodName = preg_replace('/[^a-z0-9]/i', '', $methodName);

                        if ($reflectionClass) {
                            if ($reflectionClass->hasMethod($methodName)) {
                                $event['method'] = $methodName;
                                break;
                            }
                            if ($reflectionClass->hasMethod('__invoke')) {
                                $event['method'] = '__invoke';
                                break;
                            }
                        } else {
                            $event['method'] = $methodName;
                            break;
                        }
                    }

                    if (!isset($event['method'])) {
                        throw new InvalidArgumentException(\sprintf('None of the "%s" or "__invoke" methods exist for the service "%s". Please define the "method" attribute on "kernel.event_listener" tags.', $methodName, $id));
                    }
                }

                $dispatcherDefinition = $globalDispatcherDefinition;
                if (isset($event['dispatcher'])) {
                    $dispatcherDefinition = $container->findDefinition($event['dispatcher']);
                }

                foreach ($event['events'] as $eventName) {
                    $dispatcherDefinition->addMethodCall('addListener', [$eventName, [new ServiceClosureArgument(new Reference($id)), $event['method']], $priority]);
                    if (isset($this->hotPathEvents[$eventName])) {
                        $container->getDefinition($id)->addTag('container.hot_path');
                    } elseif (isset($this->noPreloadEvents[$eventName])) {
                        ++$noPreload;
                    }
                }
            }

            if ($noPreload && \count($events) === $noPreload) {
                $container->getDefinition($id)->addTag('container.no_preload');
            }
        }

        $extractingDispatcher = new ExtractingEventDispatcher();

        foreach ($container->findTaggedServiceIds('kernel.event_subscriber', true) as $id => $tags) {
            $def = $container->getDefinition($id);

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $def->getClass();

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$r->isSubclassOf(EventSubscriberInterface::class)) {
                throw new InvalidArgumentException(\sprintf('Service "%s" must implement interface "%s".', $id, EventSubscriberInterface::class));
            }
            $class = $r->name;

            $dispatcherDefinitions = [];
            foreach ($tags as $attributes) {
                if (!isset($attributes['dispatcher']) || isset($dispatcherDefinitions[$attributes['dispatcher']])) {
                    continue;
                }

                $dispatcherDefinitions[$attributes['dispatcher']] = $container->findDefinition($attributes['dispatcher']);
            }

            if (!$dispatcherDefinitions) {
                $dispatcherDefinitions = [$globalDispatcherDefinition];
            }

            $noPreload = 0;
            ExtractingEventDispatcher::$aliases = $aliases;
            ExtractingEventDispatcher::$subscriber = $class;
            $extractingDispatcher->addSubscriber($extractingDispatcher);
            foreach ($extractingDispatcher->listeners as $args) {
                $args[1] = [new ServiceClosureArgument(new Reference($id)), $args[1]];
                foreach ($dispatcherDefinitions as $dispatcherDefinition) {
                    $dispatcherDefinition->addMethodCall('addListener', $args);
                }

                if (isset($this->hotPathEvents[$args[0]])) {
                    $container->getDefinition($id)->addTag('container.hot_path');
                } elseif (isset($this->noPreloadEvents[$args[0]])) {
                    ++$noPreload;
                }
            }
            if ($noPreload && \count($extractingDispatcher->listeners) === $noPreload) {
                $container->getDefinition($id)->addTag('container.no_preload');
            }
            $extractingDispatcher->listeners = [];
            ExtractingEventDispatcher::$aliases = [];
        }
    }

    /**
     * @return string[]
     */
    private function getEventFromTypeDeclaration(ContainerBuilder $container, string $id, string $method): array
    {
        if (
            null === ($class = $container->getDefinition($id)->getClass())
            || !($r = $container->getReflectionClass($class, false))
            || !$r->hasMethod($method)
            || 1 > ($m = $r->getMethod($method))->getNumberOfParameters()
        ) {
            throw new InvalidArgumentException(\sprintf('Service "%s" must define the "events" attribute on "kernel.event_listener" tags.', $id));
        }

        $type = $m->getParameters()[0]->getType();
        if ($type instanceof \ReflectionNamedType) {
            if ($type->isBuiltin() || Event::class === ($name = $type->getName())) {
                throw new InvalidArgumentException(\sprintf('Service "%s" must define the "events" attribute on "kernel.event_listener" tags.', $id));
            }

            return [$name];
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $type) {
                if (!$type->isBuiltin()) {
                    $types[] = $type->getName();
                }
            }

            if ($types) {
                return $types;
            }
        }

        throw new InvalidArgumentException(\sprintf('Service "%s" must define the "events" attribute on "kernel.event_listener" tags.', $id));
    }
}

/**
 * @internal
 */
class ExtractingEventDispatcher extends EventDispatcher implements EventSubscriberInterface
{
    public array $listeners = [];

    public static array $aliases = [];
    public static string $subscriber;

    public function addListener(string $eventName, callable|array $listener, int $priority = 0): void
    {
        $this->listeners[] = [$eventName, $listener[1], $priority];
    }

    public static function getSubscribedEvents(): array
    {
        $events = [];

        foreach ([self::$subscriber, 'getSubscribedEvents']() as $eventName => $params) {
            $events[self::$aliases[$eventName] ?? $eventName] = $params;
        }

        return $events;
    }
}

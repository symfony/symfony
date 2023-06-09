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

    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('event_dispatcher') && !$container->hasAlias('event_dispatcher')) {
            return;
        }

        $aliases = [];

        if ($container->hasParameter('event_dispatcher.event_aliases')) {
            $aliases = $container->getParameter('event_dispatcher.event_aliases');
        }

        $globalDispatcherDefinition = $container->findDefinition('event_dispatcher');

        $this->computeBeforeAfterPriority($container);

        foreach ($container->findTaggedServiceIds('kernel.event_listener', true) as $id => $events) {
            $noPreload = 0;

            foreach ($events as $event) {
                $priority = $event['priority'] ?? 0;

                if (!isset($event['event'])) {
                    if ($container->getDefinition($id)->hasTag('kernel.event_subscriber')) {
                        continue;
                    }

                    $event['method'] ??= '__invoke';
                    $event['event'] = $this->getEventFromTypeDeclaration($container, $id, $event['method']);
                }

                $event['event'] = $aliases[$event['event']] ?? $event['event'];

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace_callback([
                        '/(?<=\b|_)[a-z]/i',
                        '/[^a-z0-9]/i',
                    ], fn ($matches) => strtoupper($matches[0]), $event['event']);
                    $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);

                    if (null !== ($class = $container->getDefinition($id)->getClass()) && ($r = $container->getReflectionClass($class, false)) && !$r->hasMethod($event['method']) && $r->hasMethod('__invoke')) {
                        $event['method'] = '__invoke';
                    }
                }

                $dispatcherDefinition = $globalDispatcherDefinition;
                if (isset($event['dispatcher'])) {
                    $dispatcherDefinition = $container->findDefinition($event['dispatcher']);
                }

                $dispatcherDefinition->addMethodCall('addListener', [$event['event'], [new ServiceClosureArgument(new Reference($id)), $event['method']], $priority]);

                if (isset($this->hotPathEvents[$event['event']])) {
                    $container->getDefinition($id)->addTag('container.hot_path');
                } elseif (isset($this->noPreloadEvents[$event['event']])) {
                    ++$noPreload;
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
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$r->isSubclassOf(EventSubscriberInterface::class)) {
                throw new InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, EventSubscriberInterface::class));
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

    private function getEventFromTypeDeclaration(ContainerBuilder $container, string $id, string $method): string
    {
        if (
            null === ($class = $container->getDefinition($id)->getClass())
            || !($r = $container->getReflectionClass($class, false))
            || !$r->hasMethod($method)
            || 1 > ($m = $r->getMethod($method))->getNumberOfParameters()
            || !($type = $m->getParameters()[0]->getType()) instanceof \ReflectionNamedType
            || $type->isBuiltin()
            || Event::class === ($name = $type->getName())
        ) {
            throw new InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "kernel.event_listener" tags.', $id));
        }

        return $name;
    }

    private function computeBeforeAfterPriority(ContainerBuilder $container): void
    {
        $listeners = [];
        foreach ($container->findTaggedServiceIds('kernel.event_listener', true) as $id => $events) {
            foreach ($events as $event) {
                if (!isset($event['event'])) {
                    if ($container->getDefinition($id)->hasTag('kernel.event_subscriber')) {
                        continue;
                    }

                    $event['method'] ??= '__invoke';
                    $event['event'] = $this->getEventFromTypeDeclaration($container, $id, $event['method']);
                }

                $listeners[$event['event']] ??= [];

                if (isset($event['before']) || isset($event['after'])) {
                    if (isset($event['before']) && isset($event['after'])) {
                        throw new InvalidArgumentException(sprintf('Bad listener definition for "%s": cannot set "after" and "before" at the same time.', $id));
                    }

                    if ($container->findDefinition($id) === $container->findDefinition($event['before'] ?? $event['after'])) {
                        throw new InvalidArgumentException(sprintf('Bad listener definition for "%s": listener cannot self-reference in "before" or "after" definition.', $id, $event['event']));
                    }

                    if (($event['priority'] ?? 0) !== 0 && (isset($event['before']) || isset($event['after']))) {
                        throw new InvalidArgumentException(sprintf('Bad listener definition for "%s": cannot set "priority" and "before" at the same time.', $id));
                    }
                    $listeners[$event['event']][$id] = ['before' => $event['before'] ?? null, 'after' => $event['after'] ?? null];
                } else {
                    $listeners[$event['event']][$id] = ['priority' => $event['priority'] ?? 0];
                }
            }
        }

        foreach ($listeners as $eventName => $listenersForEvent) {
            foreach ($listenersForEvent as $listenerName => $listenerDefinition) {
                if (isset($listenerDefinition['before']) || isset($listenerDefinition['after'])) {
                    if ($listenerDefinition['before'] && !isset($listenersForEvent[$listenerDefinition['before']])) {
                        throw new InvalidArgumentException(sprintf('Given "before" "%s" for listener "%s" does not listen the same event.', $listenerDefinition['before'], $listenerName));
                    }

                    if ($listenerDefinition['after'] && !isset($listenersForEvent[$listenerDefinition['after']])) {
                        throw new InvalidArgumentException(sprintf('Given "after" "%s" for listener "%s" does not listen the same event.', $listenerDefinition['after'], $listenerName));
                    }

                    $this->updateListenerPriority(
                        $container,
                        $eventName,
                        $listenerName,
                        $this->computePriority($listenersForEvent, $listenerName)
                    );
                }
            }
        }
    }

    private function computePriority(array $listeners, string $listenerName, array $alreadyVisited = []): int
    {
        if ($alreadyVisited[$listenerName] ?? false) {
            throw new InvalidArgumentException(sprintf('Circular reference detected for listener "%s".', $listenerName));
        }

        $alreadyVisited[$listenerName] = true;

        if (isset($listeners[$listenerName]['before'])) {
            return $this->computePriority($listeners, $listeners[$listenerName]['before'], $alreadyVisited) + 1;
        }

        if (isset($listeners[$listenerName]['after'])) {
            return $this->computePriority($listeners, $listeners[$listenerName]['after'], $alreadyVisited) - 1;
        }

        return $listeners[$listenerName]['priority'] ?? 0;
    }

    private function updateListenerPriority(ContainerBuilder $container, string $eventName, string $listenerName, int $priority): void
    {
        $listenerDefinition = $container->getDefinition($listenerName);
        $events = $listenerDefinition->getTag('kernel.event_listener');

        foreach ($events as &$event) {
            if ($event['event'] === $eventName) {
                $event['priority'] = $priority;
                break;
            }
        }

        $listenerDefinition->clearTag('kernel.event_listener');
        $listenerDefinition->addTag('kernel.event_listener', $events);
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

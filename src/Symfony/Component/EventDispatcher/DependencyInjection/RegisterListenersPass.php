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

        // collect all listeners, and prevent keys overriding for a very unlikely case where a service is both a listener and a subscriber
        $allListenerDefinitions = array_merge_recursive(
            iterator_to_array($this->collectListeners($container)),
            iterator_to_array($this->collectSubscribers($container)),
        );

        // $allListenersMap['event_name']['listener_service_id']['method'] => $listenerDefinition.
        $allListenersMap = $this->initializeAllListenersMaps($allListenerDefinitions);

        // $listenerClassesMap['listener_FQCN']['event_name'][] => array{serviceId: string, method:string}.
        $listenerClassesMap = $this->initializeListenersClassesMap($container, $allListenerDefinitions);

        $this->handleBeforeAfter($allListenerDefinitions, $container, $allListenersMap, $listenerClassesMap);

        $this->registerListeners($container, $allListenerDefinitions);
    }

    /**
     * @param array<string, list<array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>> $allListenerDefinitions
     *
     * @return array<string, array<string, array<string, array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>>>
     */
    private function initializeAllListenersMaps(array $allListenerDefinitions): array
    {
        $allListenersMap = [];

        foreach ($allListenerDefinitions as $listenerDefinitions) {
            foreach ($listenerDefinitions as $listenerDefinition) {
                $allListenersMap[$listenerDefinition['event']][$listenerDefinition['serviceId']][$listenerDefinition['method']] = $listenerDefinition;
            }
        }

        return $allListenersMap;
    }

    /**
     * @param array<string, list<array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>> $allListenerDefinitions
     *
     * @return array<string, array<string, list<array{serviceId: string, method:string}>>>
     */
    private function initializeListenersClassesMap(ContainerBuilder $container, array $allListenerDefinitions): array
    {
        $listenerClassesMap = [];

        foreach ($allListenerDefinitions as $listenerDefinitions) {
            foreach ($listenerDefinitions as $listenerDefinition) {
                $listenerClass = $container->getDefinition($listenerDefinition['serviceId'])->getClass();

                if ($listenerClass) {
                    $listenerClassesMap[$listenerClass][$listenerDefinition['event']] ??= [];
                    $listenerClassesMap[$listenerClass][$listenerDefinition['event']][] = [
                        'serviceId' => $listenerDefinition['serviceId'],
                        'method' => $listenerDefinition['method'],
                    ];
                }
            }
        }

        return $listenerClassesMap;
    }

    /**
     * @param array<string, list<array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>>                         $allListenerDefinitions
     * @param array<string, array<string, array<string, array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>>> $allListenersMap
     * @param array<string, array<string, list<array{serviceId: string, method:string}>>>                                                                                                                                                              $listenerClassesMap
     */
    private function handleBeforeAfter(array &$allListenerDefinitions, ContainerBuilder $container, array $allListenersMap, array $listenerClassesMap): void
    {
        foreach ($allListenerDefinitions as &$listenerDefinitions) {
            foreach ($listenerDefinitions as &$listenerDefinition) {
                if (isset($listenerDefinition['before']) && isset($listenerDefinition['after'])) {
                    throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": cannot use "after" and "before" at the same time.', $listenerDefinition['serviceId']));
                }

                if (isset($listenerDefinition['before']) || isset($listenerDefinition['after'])) {
                    $listenerDefinition['priority'] = $this->computeBeforeAfterPriorities($container, $listenerDefinition, $allListenersMap, $listenerClassesMap);

                    // register the new priority in listeners map
                    unset($listenerDefinition['before'], $listenerDefinition['after']);
                    $allListenersMap[$listenerDefinition['event']][$listenerDefinition['serviceId']][$listenerDefinition['method']] = $listenerDefinition;
                }
            }
        }
    }

    /**
     * @param array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}                                              $listenerDefinition
     * @param array<string, array<string, array<string, array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>>> $allListenersMap
     * @param array<string, array<string, list<array{serviceId: string, method:string}>>>                                                                                                                                                              $listenerClassesMap
     * @param array<string, bool>                                                                                                                                                                                                                      $alreadyVisited
     */
    private function computeBeforeAfterPriorities(ContainerBuilder $container, array $listenerDefinition, array $allListenersMap, array $listenerClassesMap, array $alreadyVisited = []): int
    {
        // Prevent circular references
        $listenerName = sprintf('%s::%s', $listenerDefinition['serviceId'], $listenerDefinition['method']);
        if ($alreadyVisited[$listenerName] ?? false) {
            throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": circular reference detected.', $listenerDefinition['serviceId']));
        }
        $alreadyVisited[$listenerName] = true;

        if (isset($listenerDefinition['before']) || isset($listenerDefinition['after'])) {
            ['serviceId' => $beforeAfterServiceId, 'method' => $beforeAfterMethod] = $this->normalizeBeforeAfter($container, $listenerDefinition, $allListenersMap, $listenerClassesMap);

            $beforeAfterListenerDefinition = $allListenersMap[$listenerDefinition['event']][$beforeAfterServiceId][$beforeAfterMethod];

            $priority = $this->computeBeforeAfterPriorities($container, $beforeAfterListenerDefinition, $allListenersMap, $listenerClassesMap, $alreadyVisited);

            return isset($listenerDefinition['before']) ? $priority + 1 : $priority - 1;
        }

        return $listenerDefinition['priority'] ?? 0;
    }

    /**
     * @param array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}                                              $listenerDefinition
     * @param array<string, array<string, array<string, array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>>> $allListenersMap
     * @param array<string, array<string, list<array{serviceId: string, method:string}>>>                                                                                                                                                              $listenerClassesMap
     *
     * @return array{serviceId: string, method: string}
     *
     * before/after can be defined as: class-string, service-id, or array{class?: class-string, service?: service-id, method?: string}
     * let's normalize it, and resolve the method if not given (or rise an exception if ambiguous)
     */
    private function normalizeBeforeAfter(ContainerBuilder $container, array $listenerDefinition, array $allListenersMap, array $listenerClassesMap): array
    {
        $beforeAfterDefinition = $listenerDefinition['before'] ?? $listenerDefinition['after'];
        $id = $listenerDefinition['serviceId'];
        $event = $listenerDefinition['event'];

        $listenersForEvent = $allListenersMap[$event];

        $beforeAfterMethod = null;
        $normalizedBeforeAfter = null;

        if (\is_array($beforeAfterDefinition)) {
            if (!array_is_list($beforeAfterDefinition) || 2 !== \count($beforeAfterDefinition)) {
                throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": when declaring as an array, first item must be a service id or a class and second item must be the method.', $id));
            }

            $beforeAfterMethod = $beforeAfterDefinition[1];
            $beforeAfterServiceOrClass = $beforeAfterDefinition[0];
        } else {
            $beforeAfterServiceOrClass = $beforeAfterDefinition;
        }

        $beforeAfterDefinitionAsString = \is_string($beforeAfterDefinition) ? $beforeAfterDefinition : sprintf('%s::%s()', $beforeAfterDefinition[0], $beforeAfterDefinition[1]);

        if (class_exists($beforeAfterServiceOrClass) && !$container->has($beforeAfterServiceOrClass)) {
            if (!isset($listenerClassesMap[$beforeAfterServiceOrClass])) {
                throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" is not a listener.', $id, $beforeAfterDefinitionAsString));
            }

            if (!isset($listenerClassesMap[$beforeAfterServiceOrClass][$event])) {
                throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" does not listen to the same event.', $id, $beforeAfterDefinitionAsString));
            }

            $listenersForClassAndEvent = $listenerClassesMap[$beforeAfterServiceOrClass][$event];

            if (!$beforeAfterMethod) {
                if (1 < \count($listenersForClassAndEvent)) {
                    throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" is ambiguous. Please specify the "method" attribute.', $id, $beforeAfterServiceOrClass));
                }

                $normalizedBeforeAfter = $listenersForClassAndEvent[0];
            } else {
                foreach ($listenersForClassAndEvent as ['serviceId' => $serviceId, 'method' => $methodFromListenerDefinition]) {
                    if ($methodFromListenerDefinition === $beforeAfterMethod) {
                        $normalizedBeforeAfter = ['serviceId' => $serviceId, 'method' => $beforeAfterMethod];
                        break;
                    }
                }

                if (!isset($normalizedBeforeAfter)) {
                    throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" is not a listener.', $id, $beforeAfterDefinitionAsString));
                }
            }
        } elseif (
            $container->has($beforeAfterServiceOrClass)
            && (($def = $container->findDefinition($beforeAfterServiceOrClass))->hasTag('kernel.event_listener') || $def->hasTag('kernel.event_subscriber'))
        ) {
            if (!isset($listenersForEvent[$beforeAfterServiceOrClass])) {
                throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" does not listen to the same event.', $id, $beforeAfterDefinitionAsString));
            }

            if (!$beforeAfterMethod) {
                if (1 < \count($listenersForEvent[$beforeAfterServiceOrClass])) {
                    throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" is ambiguous. Please specify the "method" attribute.', $id, $beforeAfterServiceOrClass));
                }

                $beforeAfterMethod = array_key_first($listenersForEvent[$beforeAfterServiceOrClass]);
            } else {
                if (!isset($listenersForEvent[$beforeAfterServiceOrClass][$beforeAfterMethod])) {
                    throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" is not a listener.', $id, $beforeAfterDefinitionAsString));
                }
            }

            $normalizedBeforeAfter = ['serviceId' => $beforeAfterServiceOrClass, 'method' => $beforeAfterMethod];
        } else {
            throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" is not a listener.', $id, $beforeAfterDefinitionAsString));
        }

        if ($listenersForEvent[$normalizedBeforeAfter['serviceId']][$normalizedBeforeAfter['method']]['dispatchers'] !== $listenerDefinition['dispatchers']) {
            throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": given definition "%s" is not handled by the same dispatchers.', $id, $beforeAfterDefinitionAsString));
        }

        return $normalizedBeforeAfter;
    }

    /**
     * @param array<string, list<array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>> $allListenerDefinitions
     */
    public function registerListeners(ContainerBuilder $container, array $allListenerDefinitions): void
    {
        $globalDispatcherDefinition = $container->findDefinition('event_dispatcher');

        foreach ($allListenerDefinitions as $id => $listenerDefinitions) {
            $noPreload = 0;

            foreach ($listenerDefinitions as $listenerDefinition) {
                $dispatcherDefinitions = [];
                foreach ($listenerDefinition['dispatchers'] as $dispatcher) {
                    $dispatcherDefinitions[] = 'event_dispatcher' === $dispatcher ? $globalDispatcherDefinition : $container->findDefinition($dispatcher);
                }

                foreach ($dispatcherDefinitions as $dispatcherDefinition) {
                    $dispatcherDefinition->addMethodCall(
                        'addListener',
                        [
                            $listenerDefinition['event'],
                            [new ServiceClosureArgument(new Reference($id)), $listenerDefinition['method']],
                            $listenerDefinition['priority'] ?? 0,
                        ]
                    );
                }

                if (isset($this->hotPathEvents[$listenerDefinition['event']])) {
                    $container->getDefinition($id)->addTag('container.hot_path');
                } elseif (isset($this->noPreloadEvents[$listenerDefinition['event']])) {
                    ++$noPreload;
                }
            }

            if ($noPreload && \count($listenerDefinitions) === $noPreload) {
                $container->getDefinition($id)->addTag('container.no_preload');
            }
        }
    }

    /**
     * @return \Generator<string, list<ListenerDefinition>>
     */
    private function collectListeners(ContainerBuilder $container): \Generator
    {
        $aliases = $this->getEventsAliases($container);

        foreach ($container->findTaggedServiceIds('kernel.event_listener', true) as $id => $events) {
            $listenersDefinition = [];

            foreach ($events as $event) {
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

                    if (null !== ($class = $container->getDefinition($id)->getClass()) && ($r = $container->getReflectionClass($class, false)) && !$r->hasMethod($event['method'])) {
                        if (!$r->hasMethod('__invoke')) {
                            throw new InvalidArgumentException(sprintf('None of the "%s" or "__invoke" methods exist for the service "%s". Please define the "method" attribute on "kernel.event_listener" tags.', $event['method'], $id));
                        }
                        $event['method'] = '__invoke';
                    }
                }

                $event['dispatchers'] = [$event['dispatcher'] ?? 'event_dispatcher'];
                $event['serviceId'] = $id;
                unset($event['dispatcher']);

                $listenersDefinition[] = $event;
            }

            yield $id => $listenersDefinition;
        }
    }

    /**
     * @return \Generator<string, list<array{serviceId: string, event: string, method: string, before?: string|array{0: string, 1: string}, after?: string|array{0: string, 1: string}, priority?: int, dispatchers: list<string>}>>
     */
    private function collectSubscribers(ContainerBuilder $container): \Generator
    {
        $aliases = $this->getEventsAliases($container);

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

            $dispatchers = [];
            foreach ($tags as $attributes) {
                if (!isset($attributes['dispatcher']) || \in_array($attributes['dispatcher'], $dispatchers, true)) {
                    continue;
                }

                $dispatchers[] = $attributes['dispatcher'];
            }
            if (!$dispatchers) {
                $dispatchers[] = 'event_dispatcher';
            }

            sort($dispatchers);

            ExtractingEventDispatcher::$aliases = $aliases;
            ExtractingEventDispatcher::$subscriber = $class;
            $extractingDispatcher->addSubscriber($extractingDispatcher);

            yield $id => array_map(
                static fn (array $args) => [
                    'dispatchers' => array_values(array_unique($dispatchers)),
                    'event' => $args[0],
                    'method' => $args[1],
                    'priority' => $args[2],
                    'serviceId' => $id,
                ],
                $extractingDispatcher->listeners
            );

            $extractingDispatcher->listeners = [];
            ExtractingEventDispatcher::$aliases = [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function getEventsAliases(ContainerBuilder $container): array
    {
        $aliases = [];

        if ($container->hasParameter('event_dispatcher.event_aliases')) {
            $aliases = $container->getParameter('event_dispatcher.event_aliases') ?? [];
        }

        return $aliases;
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

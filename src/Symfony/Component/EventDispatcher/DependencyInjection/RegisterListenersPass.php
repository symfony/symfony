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
 *
 * @psalm-type BeforeAfterDefinition = string|array{0: string, 1: string}
 * @psalm-type ListenerDefinition = array{serviceId: string, event: string, method: string, before?: BeforeAfterDefinition, after?: BeforeAfterDefinition, priority?: int, dispatchers: list<string>}
 * @psalm-type AllListenersDefinition = array<string, list<ListenerDefinition>>
 */
class RegisterListenersPass implements CompilerPassInterface
{
    private array $hotPathEvents = [];
    private array $noPreloadEvents = [];

    /**
     * $allListenersMap['event_name']['listener_service_id']['method'] => $listenerDefinition.
     *
     * @var array<string, array<string, array<string, ListenerDefinition>>>
     */
    private array $allListenersMap = [];

    /**
     * $listenerClassesMap['listener_FQCN']['event_name'][] => array{serviceId: string, method:string}.
     *
     * @var array<string, array<string, list<array{serviceId: string, method:string}>>>
     */
    private array $listenerClassesMap = [];

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

        $this->initializeListenersMaps($container, $allListenerDefinitions);

        $this->handleBeforeAfter($allListenerDefinitions, $container);

        $this->registerListeners($container, $allListenerDefinitions);
    }

    /**
     * @param AllListenersDefinition $allListenerDefinitions
     */
    private function initializeListenersMaps(ContainerBuilder $container, array $allListenerDefinitions): void
    {
        foreach ($allListenerDefinitions as $listenerDefinitions) {
            foreach ($listenerDefinitions as $listenerDefinition) {
                $this->allListenersMap[$listenerDefinition['event']][$listenerDefinition['serviceId']][$listenerDefinition['method']] = $listenerDefinition;

                $listenerClass = $container->getDefinition($listenerDefinition['serviceId'])->getClass();

                if ($listenerClass) {
                    $this->listenerClassesMap[$listenerClass][$listenerDefinition['event']] ??= [];
                    $this->listenerClassesMap[$listenerClass][$listenerDefinition['event']][] = [
                        'serviceId' => $listenerDefinition['serviceId'],
                        'method' => $listenerDefinition['method'],
                    ];
                }
            }
        }
    }

    /**
     * @param AllListenersDefinition $allListenerDefinitions
     */
    private function handleBeforeAfter(array &$allListenerDefinitions, ContainerBuilder $container): void
    {
        foreach ($allListenerDefinitions as &$listenerDefinitions) {
            foreach ($listenerDefinitions as &$listenerDefinition) {
                if (isset($listenerDefinition['before']) && isset($listenerDefinition['after'])) {
                    throw InvalidBeforeAfterListenerDefinitionException::beforeAndAfterAtSameTime($listenerDefinition['serviceId']);
                }

                if (isset($listenerDefinition['before']) || isset($listenerDefinition['after'])) {
                    $listenerDefinition['priority'] = $this->computeBeforeAfterPriorities($container, $listenerDefinition);

                    // register the new priority in listeners map
                    unset($listenerDefinition['before'], $listenerDefinition['after']);
                    $this->allListenersMap[$listenerDefinition['event']][$listenerDefinition['serviceId']][$listenerDefinition['method']] = $listenerDefinition;
                }
            }
        }
    }

    /**
     * @param ListenerDefinition  $listenerDefinition
     * @param array<string, bool> $alreadyVisited
     */
    private function computeBeforeAfterPriorities(ContainerBuilder $container, array $listenerDefinition, array $alreadyVisited = []): int
    {
        // Prevent circular references
        $listenerName = sprintf('%s::%s', $listenerDefinition['serviceId'], $listenerDefinition['method']);
        if ($alreadyVisited[$listenerName] ?? false) {
            throw InvalidBeforeAfterListenerDefinitionException::circularReference($listenerDefinition['serviceId']);
        }
        $alreadyVisited[$listenerName] = true;

        if (isset($listenerDefinition['before']) || isset($listenerDefinition['after'])) {
            ['serviceId' => $beforeAfterServiceId, 'method' => $beforeAfterMethod] = $this->normalizeBeforeAfter($container, $listenerDefinition);

            $beforeAfterListenerDefinition = $this->allListenersMap[$listenerDefinition['event']][$beforeAfterServiceId][$beforeAfterMethod];

            $priority = $this->computeBeforeAfterPriorities($container, $beforeAfterListenerDefinition, $alreadyVisited);

            return isset($listenerDefinition['before']) ? $priority + 1 : $priority - 1;
        }

        return $listenerDefinition['priority'] ?? 0;
    }

    /**
     * @param ListenerDefinition $listenerDefinition
     *
     * @return array{serviceId: string, method: string}
     *
     * before/after can be defined as: class-string, service-id, or array{class?: class-string, service?: service-id, method?: string}
     * let's normalize it, and resolve the method if not given (or rise an exception if ambiguous)
     */
    private function normalizeBeforeAfter(ContainerBuilder $container, array $listenerDefinition): array
    {
        $beforeAfterDefinition = $listenerDefinition['before'] ?? $listenerDefinition['after'];
        $id = $listenerDefinition['serviceId'];
        $event = $listenerDefinition['event'];

        $listenersForEvent = $this->allListenersMap[$event];

        $beforeAfterMethod = null;
        $normalizedBeforeAfter = null;

        if (\is_array($beforeAfterDefinition)) {
            if (!array_is_list($beforeAfterDefinition) || 2 !== \count($beforeAfterDefinition)) {
                throw InvalidBeforeAfterListenerDefinitionException::arrayDefinitionInvalid($id);
            }

            $beforeAfterMethod = $beforeAfterDefinition[1];
            $beforeAfterServiceOrClass = $beforeAfterDefinition[0];
        } else {
            $beforeAfterServiceOrClass = $beforeAfterDefinition;
        }

        if (class_exists($beforeAfterServiceOrClass) && !$container->has($beforeAfterServiceOrClass)) {
            if (!isset($this->listenerClassesMap[$beforeAfterServiceOrClass])) {
                throw InvalidBeforeAfterListenerDefinitionException::notAListener($id, $beforeAfterDefinition);
            }

            if (!isset($this->listenerClassesMap[$beforeAfterServiceOrClass][$event])) {
                throw InvalidBeforeAfterListenerDefinitionException::notSameEvent($id, $beforeAfterDefinition);
            }

            $listenersForClassAndEvent = $this->listenerClassesMap[$beforeAfterServiceOrClass][$event];

            if (!$beforeAfterMethod) {
                if (1 < \count($listenersForClassAndEvent)) {
                    throw InvalidBeforeAfterListenerDefinitionException::ambiguousDefinition($id, $beforeAfterServiceOrClass);
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
                    throw InvalidBeforeAfterListenerDefinitionException::notAListener($id, $beforeAfterDefinition);
                }
            }
        } elseif (
            $container->has($beforeAfterServiceOrClass)
            && (($def = $container->findDefinition($beforeAfterServiceOrClass))->hasTag('kernel.event_listener') || $def->hasTag('kernel.event_subscriber'))
        ) {
            if (!isset($listenersForEvent[$beforeAfterServiceOrClass])) {
                throw InvalidBeforeAfterListenerDefinitionException::notSameEvent($id, $beforeAfterDefinition);
            }

            if (!$beforeAfterMethod) {
                if (1 < \count($listenersForEvent[$beforeAfterServiceOrClass])) {
                    throw InvalidBeforeAfterListenerDefinitionException::ambiguousDefinition($id, $beforeAfterServiceOrClass);
                }

                $beforeAfterMethod = array_key_first($listenersForEvent[$beforeAfterServiceOrClass]);
            } else {
                if (!isset($listenersForEvent[$beforeAfterServiceOrClass][$beforeAfterMethod])) {
                    throw InvalidBeforeAfterListenerDefinitionException::notAListener($id, $beforeAfterDefinition);
                }
            }

            $normalizedBeforeAfter = ['serviceId' => $beforeAfterServiceOrClass, 'method' => $beforeAfterMethod];
        } else {
            throw InvalidBeforeAfterListenerDefinitionException::notAListener($id, $beforeAfterDefinition);
        }

        if ($listenersForEvent[$normalizedBeforeAfter['serviceId']][$normalizedBeforeAfter['method']]['dispatchers'] !== $listenerDefinition['dispatchers']) {
            throw InvalidBeforeAfterListenerDefinitionException::notSameDispatchers($id, $beforeAfterDefinition);
        }

        return $normalizedBeforeAfter;
    }

    /**
     * @param AllListenersDefinition $allListenerDefinitions
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
     * @return \Generator<string, list<ListenerDefinition>>
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
            $aliases = $container->getParameter('event_dispatcher.event_aliases');
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

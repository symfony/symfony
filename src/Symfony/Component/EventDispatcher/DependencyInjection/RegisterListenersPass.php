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

        $listerDefinitions = new ListenerDefinitionsIterator([
            ...iterator_to_array($this->collectListeners($container)),
            ...iterator_to_array($this->collectSubscribers($container)),
        ], $container
        );

        $globalDispatcherDefinition = $container->findDefinition('event_dispatcher');

        foreach ($listerDefinitions->iterate() as $id => $listenerDefinitions) {
            $noPreload = 0;

            foreach ($listenerDefinitions as $listenerDefinition) {
                $dispatcherDefinitions = [];
                foreach ($listenerDefinition->dispatchers as $dispatcher) {
                    $dispatcherDefinitions[] = 'event_dispatcher' === $dispatcher ? $globalDispatcherDefinition : $container->findDefinition($dispatcher);
                }

                foreach ($dispatcherDefinitions as $dispatcherDefinition) {
                    $dispatcherDefinition->addMethodCall(
                        'addListener',
                        [
                            $listenerDefinition->event,
                            [new ServiceClosureArgument(new Reference($id)), $listenerDefinition->method],
                            $listenerDefinition->priority ?? 0,
                        ]
                    );
                }

                if (isset($this->hotPathEvents[$listenerDefinition->event])) {
                    $container->getDefinition($id)->addTag('container.hot_path');
                } elseif (isset($this->noPreloadEvents[$listenerDefinition->event])) {
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

                yield new ListenerDefinition(
                    serviceId: $id,
                    event: $event['event'],
                    method: $event['method'],
                    priority: $event['priority'] ?? 0,
                    dispatchers: $event['dispatchers'],
                    before: $event['before'] ?? null,
                    after: $event['after'] ?? null,
                );
            }
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

            foreach ($extractingDispatcher->listeners as $listener) {
                yield new ListenerDefinition(
                    serviceId: $id,
                    event: $listener[0],
                    method: $listener[1],
                    priority: $listener[2],
                    dispatchers: array_values(array_unique($dispatchers)),
                    before: null,
                    after: null,
                );
            }

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

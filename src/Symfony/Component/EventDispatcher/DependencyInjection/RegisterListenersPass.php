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
use Symfony\Component\DependencyInjection\Compiler\BeforeAfterSorter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
     *
     * @deprecated since Symfony 8.1, use AddEventAliasesPass instead
     */
    public function setHotPathEvents(array $hotPathEvents): static
    {
        trigger_deprecation('symfony/event-dispatcher', '8.1', 'The "%s()" method is deprecated, register an "%s" compiler pass with the hot-path events instead.', __METHOD__, AddEventAliasesPass::class);

        $this->hotPathEvents = array_flip($hotPathEvents);

        return $this;
    }

    /**
     * @return $this
     *
     * @deprecated since Symfony 8.1, use AddEventAliasesPass instead
     */
    public function setNoPreloadEvents(array $noPreloadEvents): static
    {
        trigger_deprecation('symfony/event-dispatcher', '8.1', 'The "%s()" method is deprecated, register an "%s" compiler pass with the no-preload events instead.', __METHOD__, AddEventAliasesPass::class);

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

        $hotPathEvents = $this->hotPathEvents;
        $noPreloadEvents = $this->noPreloadEvents;

        if ($container->hasParameter('event_dispatcher.hot_path_events')) {
            $hotPathEvents += array_flip($container->getParameter('event_dispatcher.hot_path_events'));
        }

        if ($container->hasParameter('event_dispatcher.no_preload_events')) {
            $noPreloadEvents += array_flip($container->getParameter('event_dispatcher.no_preload_events'));
        }

        $globalDispatcherDefinition = $container->findDefinition('event_dispatcher');
        $calls = [];

        foreach ($container->findTaggedServiceIds('kernel.event_listener', true) as $id => $events) {
            $noPreload = 0;

            $resolvedEvents = [];
            foreach ($events as $event) {
                if (!isset($event['event'])) {
                    if ($container->getDefinition($id)->hasTag('kernel.event_subscriber')) {
                        continue;
                    }

                    $event['method'] ??= '__invoke';
                    $eventNames = $this->getEventFromTypeDeclaration($container, $id, $event['method']);
                } else {
                    $eventNames = [$event['event']];
                }

                foreach ($eventNames as $eventName) {
                    $event['event'] = $aliases[$eventName] ?? $eventName;
                    $resolvedEvents[] = $event;
                }
            }

            foreach ($resolvedEvents as $event) {
                $priority = $event['priority'] ?? 0;

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace_callback([
                        '/(?<=\b|_)[a-z]/i',
                        '/[^a-z0-9]/i',
                    ], static fn ($matches) => strtoupper($matches[0]), $event['event']);
                    $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);

                    if (null !== ($class = $container->getDefinition($id)->getClass()) && ($r = $container->getReflectionClass($class, false)) && !$r->hasMethod($event['method'])) {
                        if (!$r->hasMethod('__invoke')) {
                            throw new InvalidArgumentException(\sprintf('None of the "%s" or "__invoke" methods exist for the service "%s". Please define the "method" attribute on "kernel.event_listener" tags.', $event['method'], $id));
                        }

                        $event['method'] = '__invoke';
                    }
                }

                $dispatcherDefinition = $globalDispatcherDefinition;
                if (isset($event['dispatcher'])) {
                    $dispatcherDefinition = $container->findDefinition($event['dispatcher']);
                }

                $constraints = [];
                foreach (['before', 'after'] as $direction) {
                    if ($targets = (array) ($event[$direction] ?? [])) {
                        $constraints[$direction] = $targets;
                    }
                }

                $calls[] = [$dispatcherDefinition, $id, [$event['event'], [new ServiceClosureArgument(new Reference($id)), $event['method']], $priority], $constraints];

                if (isset($hotPathEvents[$event['event']])) {
                    $container->getDefinition($id)->addTag('container.hot_path');
                } elseif (isset($noPreloadEvents[$event['event']])) {
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
                    $calls[] = [$dispatcherDefinition, $id, $args, []];
                }

                if (isset($hotPathEvents[$args[0]])) {
                    $container->getDefinition($id)->addTag('container.hot_path');
                } elseif (isset($noPreloadEvents[$args[0]])) {
                    ++$noPreload;
                }
            }
            if ($noPreload && \count($extractingDispatcher->listeners) === $noPreload) {
                $container->getDefinition($id)->addTag('container.no_preload');
            }
            $extractingDispatcher->listeners = [];
            ExtractingEventDispatcher::$aliases = [];
        }

        $this->addListenerCalls($container, $calls);
    }

    /**
     * @param list<array{0: Definition, 1: string, 2: array, 3: array}> $calls Dispatcher definition, service id, addListener() arguments and "before"/"after" constraints
     */
    private function addListenerCalls(ContainerBuilder $container, array $calls): void
    {
        $groups = [];

        foreach ($calls as $i => [$dispatcherDefinition, , $args]) {
            $groups[spl_object_id($dispatcherDefinition)."\0".$args[0]][] = $i;
        }

        foreach ($groups as $group) {
            foreach ($this->sortByConstraints($container, $calls, $group) as $i => $call) {
                $calls[$group[$i]] = $call;
            }
        }

        foreach ($calls as [$dispatcherDefinition, , $args]) {
            $dispatcherDefinition->addMethodCall('addListener', $args);
        }
    }

    /**
     * Rejects a "Service::method" target naming a method the service does not listen to.
     *
     * A target whose service part is absent stays ignored: the package declaring it may not be installed.
     * One that is present has to name a real listener, otherwise the constraint would silently do nothing.
     *
     * @param array<string, array{before?: list<string>, after?: list<string>}> $constraints
     * @param array<string, list<string>>                                       $aliases
     */
    private function checkTargetMethods(array $constraints, array $aliases, string $event): void
    {
        foreach ($constraints as $key => $constraint) {
            foreach ($constraint as $direction => $targets) {
                foreach ($targets as $target) {
                    if (isset($aliases[$target]) || false === $i = strrpos($target, '::')) {
                        continue;
                    }

                    $service = substr($target, 0, $i);

                    if (isset($aliases[$service])) {
                        throw new InvalidArgumentException(\sprintf('Invalid "%s" constraint on listener "%s": "%s" does not listen to event "%s" with method "%s".', $direction, $key, $service, $event, substr($target, 2 + $i)));
                    }
                }
            }
        }
    }

    /**
     * Reorders the calls listening to one event on one dispatcher so that their "before"/"after" constraints are met.
     *
     * The runtime order is (priority DESC, insertion ASC), so the constraints are turned into an insertion
     * order, and priorities are raised whenever a constraint puts a listener ahead of a higher-priority one.
     *
     * @param list<array{0: Definition, 1: string, 2: array, 3: array}> $calls
     * @param list<int>                                                 $group The indexes of the calls to reorder
     *
     * @return list<array{0: Definition, 1: string, 2: array, 3: array}> The reordered calls, or none when the group has no constraint to apply
     */
    private function sortByConstraints(ContainerBuilder $container, array $calls, array $group): array
    {
        $constrained = false;

        foreach ($group as $i) {
            if ($calls[$i][3]) {
                $constrained = true;
                break;
            }
        }

        if (!$constrained) {
            return [];
        }

        $seed = $group;
        usort($seed, static fn ($a, $b) => $calls[$b][2][2] <=> $calls[$a][2][2] ?: $a <=> $b);

        $keys = [];
        $indexes = [];
        $aliases = [];
        $constraints = [];

        foreach ($seed as $i) {
            $id = $calls[$i][1];

            for ($n = 0, $key = $id; isset($indexes[$key]); ++$n) {
                $key = $id.'#'.$n;
            }

            $method = $calls[$i][2][1][1];
            $aliases[$id][] = $key;
            $aliases[$id.'::'.$method][] = $key;
            $indexes[$key] = $i;
            $keys[] = $key;

            if ($calls[$i][3]) {
                $constraints[$key] = $calls[$i][3];
            }

            if ($class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass())) {
                $aliases[$class][] = $key;
                $aliases[$class.'::'.$method][] = $key;
            }
        }

        // a service id always designates its own registrations, whatever class it shares a name with
        foreach ($seed as $i) {
            $id = $calls[$i][1];
            $aliases[$id] = array_values(array_unique($aliases[$id]));
            $aliases[$id.'::'.$calls[$i][2][1][1]] = array_values(array_unique($aliases[$id.'::'.$calls[$i][2][1][1]]));
        }

        $this->checkTargetMethods($constraints, $aliases, $calls[$group[0]][2][0]);

        try {
            $sortedKeys = BeforeAfterSorter::sort($keys, $constraints, $aliases);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf('Invalid "before"/"after" constraints for event "%s": ', $calls[$group[0]][2][0]).lcfirst($e->getMessage()), previous: $e);
        }

        if ($sortedKeys === $keys) {
            // the constraints changed nothing, leave the calls exactly as they were emitted
            return [];
        }

        $sorted = [];
        foreach ($sortedKeys as $key) {
            $sorted[] = $calls[$indexes[$key]];
        }

        $floor = \PHP_INT_MIN;
        for ($i = \count($sorted) - 1; 0 <= $i; --$i) {
            $sorted[$i][2][2] = $floor = max($sorted[$i][2][2], $floor);
        }

        return $sorted;
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
            || !(($type = $m->getParameters()[0]->getType()) instanceof \ReflectionNamedType || $type instanceof \ReflectionUnionType)
        ) {
            throw new InvalidArgumentException(\sprintf('Service "%s" must define the "event" attribute on "kernel.event_listener" tags.', $id));
        }

        $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];

        $names = [];
        foreach ($types as $type) {
            if (!$type instanceof \ReflectionNamedType
                || $type->isBuiltin()
                || Event::class === ($name = $type->getName())
            ) {
                continue;
            }

            $names[] = $name;
        }

        if (!$names) {
            throw new InvalidArgumentException(\sprintf('Service "%s" must define the "event" attribute on "kernel.event_listener" tags.', $id));
        }

        return $names;
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

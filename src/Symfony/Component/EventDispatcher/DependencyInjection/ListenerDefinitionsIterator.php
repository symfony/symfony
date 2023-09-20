<?php

declare(strict_types=1);

namespace Symfony\Component\EventDispatcher\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class ListenerDefinitionsIterator
{
    private readonly array $listenerDefinitions;

    /**
     * @param list<ListenerDefinition> $listenerDefinitions
     */
    public function __construct(array $listenerDefinitions, private readonly ContainerBuilder $container)
    {
        $this->listenerDefinitions = $listenerDefinitions;
    }

    /**
     * @return array<string, list<ListenerDefinition>>
     */
    public function iterate(): array
    {
        $listeners = [];

        foreach ($this->listenerDefinitions as $listener) {
            $listeners[$listener->serviceId] ??= [];
            $listeners[$listener->serviceId][] = $listener->withPriority($this->getPriorityFor($listener));
        }

        return $listeners;
    }

    private function getPriorityFor(ListenerDefinition $listener, array $alreadyVisited = []): int
    {
        if ($alreadyVisited[$listener->name()] ?? false) {
            throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": circular reference detected.', array_key_first($alreadyVisited)));
        }

        $alreadyVisited[$listener->name()] = true;

        if (!$listener->beforeAfterService) {
            return $listener->priority;
        }

        $beforeAfterListeners = $this->matchingBeforeAfterListeners($listener);

        $beforeAfterListener = match (true) {
            !$beforeAfterListeners => throw new InvalidArgumentException(
                sprintf('Invalid before/after definition for service "%s": "%s" does not listen to the same event.', $listener->serviceId, $listener->printableBeforeAfterDefinition())
            ),
            !$listener->beforeAfterMethod && count($beforeAfterListeners) === 1 => current($beforeAfterListeners),
            !$listener->beforeAfterMethod && count($beforeAfterListeners) > 1 => throw new InvalidArgumentException(
                sprintf('Invalid before/after definition for service "%s": "%s" has multiple methods. Please specify the "method" attribute.', $listener->serviceId, $listener->printableBeforeAfterDefinition())
            ),
            $listener->beforeAfterMethod && !isset($beforeAfterListeners[$listener->beforeAfterMethod]) => throw new InvalidArgumentException(
                sprintf('Invalid before/after definition for service "%s": method "%s" does not exist or is not a listener.', $listener->serviceId, $listener->printableBeforeAfterDefinition())
            ),
            $listener->beforeAfterMethod && isset($beforeAfterListeners[$listener->beforeAfterMethod]) => $beforeAfterListeners[$listener->beforeAfterMethod],
            default => new \LogicException('This should never happen')
        };

        if ($beforeAfterListener->dispatchers !== $listener->dispatchers) {
            throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": "%s" is not handled by the same dispatchers.', $listener->serviceId, $listener->printableBeforeAfterDefinition()));
        }

        return $this->getPriorityFor($beforeAfterListener, $alreadyVisited) + $listener->priorityModifier;
    }

    /**
     * @return array<string, ListenerDefinition>
     */
    private function matchingBeforeAfterListeners(ListenerDefinition $listener): array
    {
        $beforeAfterService = $listener->beforeAfterService;

        if (
            $this->container->has($beforeAfterService)
            && (($def = $this->container->findDefinition($beforeAfterService))->hasTag('kernel.event_listener') || $def->hasTag('kernel.event_subscriber'))
        ) {
            $listenersWithServiceId = array_filter(
                $this->listenerDefinitions,
                static fn(ListenerDefinition $listenerDefinition) => $listenerDefinition->serviceId === $beforeAfterService && $listenerDefinition->event === $listener->event
            );

            return array_combine(
                array_map(static fn(ListenerDefinition $listenerDefinition) => $listenerDefinition->method, $listenersWithServiceId),
                $listenersWithServiceId,
            );
        }

        throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": "%s" is not a listener.', $listener->serviceId, $listener->printableBeforeAfterDefinition()));
    }
}

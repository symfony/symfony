<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Dispatches events to listeners a container described at compile time.
 *
 * The listener map holds the identifier and the method of each listener, in the
 * order they must run in. It is a constant expression in the compiled container,
 * so nothing is allocated to build it, and a listener is fetched from the
 * locator only when it is about to run.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CompiledEventDispatcher implements EventDispatcherInterface
{
    private array $services = [];
    private array $optimized = [];
    private ?EventDispatcher $dispatcher = null;

    /**
     * @param array<string, array<int, list<array{0: string, 1: string}>>> $listeners The listener id and method by event name, then by priority in the order they must run in
     */
    public function __construct(
        private array $listeners,
        private ContainerInterface $locator,
    ) {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $eventName ??= $event::class;

        if ($this->dispatcher) {
            return $this->dispatcher->dispatch($event, $eventName);
        }

        if (!$listeners = $this->optimized[$eventName] ?? $this->optimize($eventName)) {
            return $event;
        }

        $stoppable = $event instanceof StoppableEventInterface;

        foreach ($listeners as $listener) {
            if ($stoppable && $event->isPropagationStopped()) {
                break;
            }

            $listener($event, $eventName, $this);
        }

        return $event;
    }

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        ($this->dispatcher ?? $this->thaw())->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        ($this->dispatcher ?? $this->thaw())->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        ($this->dispatcher ?? $this->thaw())->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        ($this->dispatcher ?? $this->thaw())->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return ($this->dispatcher ?? $this->thaw())->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return ($this->dispatcher ?? $this->thaw())->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        if ($this->dispatcher) {
            return $this->dispatcher->hasListeners($eventName);
        }

        return null === $eventName ? (bool) $this->listeners : !empty($this->listeners[$eventName]);
    }

    /**
     * Flattens the listeners of an event, each one replacing itself with its service the first time it runs.
     */
    private function optimize(string $eventName): array
    {
        $this->optimized[$eventName] = [];
        $i = 0;

        foreach ($this->listeners[$eventName] ?? [] as $listeners) {
            foreach ($listeners as [$id, $method]) {
                $this->optimized[$eventName][$i] = function (...$args) use ($eventName, $i, $id, $method) {
                    ($this->optimized[$eventName][$i] = ($this->services[$id] ??= $this->locator->get($id))->$method(...))(...$args);
                };
                ++$i;
            }
        }

        return $this->optimized[$eventName];
    }

    /**
     * Moves the listeners to a plain dispatcher, which everything but dispatching goes through.
     */
    private function thaw(): EventDispatcher
    {
        $this->dispatcher = new EventDispatcher();
        $this->optimized = [];

        foreach ($this->listeners as $eventName => $byPriority) {
            foreach ($byPriority as $priority => $listeners) {
                foreach ($listeners as [$id, $method]) {
                    $this->dispatcher->addListener($eventName, [fn () => $this->services[$id] ??= $this->locator->get($id), $method], $priority);
                }
            }
        }

        $this->listeners = [];

        return $this->dispatcher;
    }
}

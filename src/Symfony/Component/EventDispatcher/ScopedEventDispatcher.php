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

/**
 * Adds listeners to those of another dispatcher, without mutating it.
 *
 * Use it to give a listener the lifetime of a scope, a console command or a test
 * case for example, when the dispatcher it has to run on is a shared one.
 *
 * The listeners of the wrapped dispatcher are copied over when a listener is added
 * for the same event, so that the two sets interleave by priority. An event no
 * listener was added for here is dispatched by the wrapped dispatcher itself.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ScopedEventDispatcher extends EventDispatcher
{
    private array $merged = [];

    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct();
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $eventName ??= $event::class;

        return parent::hasListeners($eventName) ? parent::dispatch($event, $eventName) : $this->dispatcher->dispatch($event, $eventName);
    }

    public function addListener(string $eventName, callable|array $listener, int $priority = 0): void
    {
        $this->merge($eventName);

        parent::addListener($eventName, $listener, $priority);
    }

    public function getListeners(?string $eventName = null): array
    {
        if (null === $eventName) {
            $listeners = [];

            foreach (array_keys(parent::getListeners() + $this->dispatcher->getListeners()) as $name) {
                $listeners[$name] = $this->getListeners($name);
            }

            return $listeners;
        }

        return parent::hasListeners($eventName) ? parent::getListeners($eventName) : $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable|array $listener): ?int
    {
        return parent::hasListeners($eventName) ? parent::getListenerPriority($eventName, $listener) : $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return parent::hasListeners($eventName) || $this->dispatcher->hasListeners($eventName);
    }

    private function merge(string $eventName): void
    {
        if ($this->merged[$eventName] ?? false) {
            return;
        }

        $this->merged[$eventName] = true;

        foreach ($this->dispatcher->getListeners($eventName) as $listener) {
            parent::addListener($eventName, $listener, $this->dispatcher->getListenerPriority($eventName, $listener) ?? 0);
        }
    }
}

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
 * Builds the listener map an EventDispatcher can be constructed with.
 *
 * The listeners of each event come out in the order the dispatcher must call
 * them in, so that it does not have to sort them itself.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SortedListeners
{
    private array $listeners = [];

    /**
     * @param mixed $listener A callable, or the arguments a container builds one from
     *
     * @return $this
     */
    public function add(string $eventName, mixed $listener, int $priority = 0): static
    {
        $this->listeners[$eventName][$priority][] = $listener;

        return $this;
    }

    public function isEmpty(): bool
    {
        return !$this->listeners;
    }

    /**
     * @return array<string, array<int, list<mixed>>>
     */
    public function toArray(): array
    {
        $listeners = $this->listeners;

        foreach ($listeners as &$byPriority) {
            krsort($byPriority);
        }

        return $listeners;
    }
}

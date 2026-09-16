<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\EventDispatcher;

/**
 * Tells which listeners are registered for an event, and in which order they run.
 */
interface ListenerIntrospectionInterface
{
    /**
     * Gets the listeners of a specific event or all listeners sorted by descending priority.
     *
     * @return ($eventName is null ? array<string, list<callable>> : list<callable>)
     */
    public function getListeners(?string $eventName = null): array;

    /**
     * Gets the listener priority for a specific event.
     *
     * Returns null if the event or the listener does not exist.
     */
    public function getListenerPriority(string $eventName, callable $listener): ?int;

    /**
     * Checks whether an event has any registered listeners.
     */
    public function hasListeners(?string $eventName = null): bool;
}

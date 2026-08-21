<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine;

use Doctrine\Common\EventArgs;
use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Psr\Container\ContainerInterface;

/**
 * Allows lazy loading of listener and subscriber services.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ContainerAwareEventManager extends EventManager
{
    private array $initialized = [];
    private bool $initializedSubscribers = false;
    private array $initializedHashMapping = [];
    /** @var array<string, \WeakMap<object, true>> */
    private array $removedListeners = [];
    private array $methods = [];

    /**
     * @param list<array{string[], string|object}> $listeners List of [events, listener] tuples
     */
    public function __construct(
        private ContainerInterface $container,
        private array $listeners = [],
    ) {
    }

    public function dispatchEvent(string $eventName, ?EventArgs $eventArgs = null): void
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        $eventArgs ??= EventArgs::getEmptyInstance();

        if (!isset($this->initialized[$eventName])) {
            $this->initializeListeners($eventName);
        }

        foreach ($this->listeners[$eventName] as $hash => $listener) {
            $listener->{$this->methods[$eventName][$hash]}($eventArgs);
        }
    }

    public function getListeners(string $event): array
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }
        if (!isset($this->initialized[$event])) {
            $this->initializeListeners($event);
        }

        return $this->listeners[$event];
    }

    public function getAllListeners(): array
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }

        foreach ($this->listeners as $event => $listeners) {
            if (!isset($this->initialized[$event])) {
                $this->initializeListeners($event);
            }
        }

        return $this->listeners;
    }

    public function hasListeners(string $event): bool
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }

        return isset($this->listeners[$event]) && $this->listeners[$event];
    }

    public function addEventListener(string|array $events, object|string $listener): void
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }

        $hash = $this->getHash($listener);

        foreach ((array) $events as $event) {
            // Overrides listener if a previous one was associated already
            // Prevents duplicate listeners on same event (same instance only)
            $this->listeners[$event][$hash] = $listener;

            if (\is_string($listener)) {
                unset($this->initialized[$event]);
                unset($this->initializedHashMapping[$event][$hash]);
            } else {
                $this->methods[$event][$hash] = $this->getMethod($listener, $event);
            }
        }
    }

    public function removeEventListener(string|array $events, object|string $listener): void
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }

        $hash = $this->getHash($listener);

        foreach ((array) $events as $event) {
            $eventHash = $hash;

            if (null !== $initializedHash = $this->initializedHashMapping[$event][$eventHash] ?? null) {
                unset($this->initializedHashMapping[$event][$eventHash]);
                $eventHash = $initializedHash;
            } elseif (\is_object($listener) && isset($this->listeners[$event]) && !isset($this->listeners[$event][$eventHash]) && !isset($this->initialized[$event])) {
                // The listener might be one of the services that are not initialized yet,
                // which are stored under the hash of their service id. Defer the removal
                // to the moment they are instantiated anyway.
                $this->removedListeners[$event] ??= new \WeakMap();
                $this->removedListeners[$event][$listener] = true;
            }

            unset($this->listeners[$event][$eventHash], $this->methods[$event][$eventHash]);
        }
    }

    public function addEventSubscriber(EventSubscriber $subscriber): void
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }

        parent::addEventSubscriber($subscriber);
    }

    public function removeEventSubscriber(EventSubscriber $subscriber): void
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }

        parent::removeEventSubscriber($subscriber);
    }

    private function initializeListeners(string $eventName): void
    {
        $removedListeners = $this->removedListeners[$eventName] ?? null;
        unset($this->removedListeners[$eventName]);

        do {
            $this->initialized[$eventName] = true;

            // We'll refill the whole array in order to keep the same order
            $listeners = [];
            foreach ($this->listeners[$eventName] as $hash => $listener) {
                if (!isset($this->listeners[$eventName][$hash])) {
                    // removed while another listener service was being built
                    continue;
                }
                unset($this->listeners[$eventName][$hash]);

                if (\is_string($listener)) {
                    $listener = $this->container->get($listener);

                    if (isset($removedListeners[$listener])) {
                        continue;
                    }

                    $newHash = $this->getHash($listener);

                    $this->initializedHashMapping[$eventName][$hash] = $newHash;

                    $listeners[$newHash] = $listener;

                    $this->methods[$eventName][$newHash] = $this->getMethod($listener, $eventName);
                } else {
                    $listeners[$hash] = $listener;
                }
            }

            // Building a listener service can add listeners for the same event.
            // What is left in $this->listeners[$eventName] is what it added.
            $this->listeners[$eventName] = $listeners + $this->listeners[$eventName];
        } while (!isset($this->initialized[$eventName]));
    }

    private function initializeSubscribers(): void
    {
        $this->initializedSubscribers = true;
        $listeners = $this->listeners;
        $this->listeners = [];
        foreach ($listeners as $listener) {
            if (\is_array($listener)) {
                $this->addEventListener(...$listener);
                continue;
            }

            throw new \InvalidArgumentException(\sprintf('Using Doctrine subscriber "%s" is not allowed. Register it as a listener instead, using e.g. the #[AsDoctrineListener] or #[AsDocumentListener] attribute.', \is_object($listener) ? get_debug_type($listener) : $listener));
        }
    }

    private function getHash(string|object $listener): string
    {
        if (\is_string($listener)) {
            return '_service_'.$listener;
        }

        return spl_object_id($listener);
    }

    private function getMethod(object $listener, string $event): string
    {
        if (!method_exists($listener, $event) && method_exists($listener, '__invoke')) {
            return '__invoke';
        }

        return $event;
    }
}

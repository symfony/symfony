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
    /**
     * Map of registered listeners.
     *
     * <event> => <listeners>
     */
    private array $listeners = [];
    private array $initialized = [];
    private bool $initializedSubscribers = false;
    private array $methods = [];
    private ContainerInterface $container;

    /**
     * @param list<array{string[], string|object}> $listeners List of [events, listener] tuples
     */
    public function __construct(ContainerInterface $container, array $listeners = [])
    {
        $this->container = $container;
        $this->listeners = $listeners;
    }

    public function dispatchEvent($eventName, EventArgs $eventArgs = null): void
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

    /**
     * @return object[][]
     */
    public function getListeners($event = null): array
    {
        if (null === $event) {
            trigger_deprecation('symfony/doctrine-bridge', '6.2', 'Calling "%s()" without an event name is deprecated. Call "getAllListeners()" instead.', __METHOD__);

            return $this->getAllListeners();
        }
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

    public function hasListeners($event): bool
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }

        return isset($this->listeners[$event]) && $this->listeners[$event];
    }

    public function addEventListener($events, $listener): void
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
            } else {
                $this->methods[$event][$hash] = $this->getMethod($listener, $event);
            }
        }
    }

    public function removeEventListener($events, $listener): void
    {
        if (!$this->initializedSubscribers) {
            $this->initializeSubscribers();
        }

        $hash = $this->getHash($listener);

        foreach ((array) $events as $event) {
            // Check if we actually have this listener associated
            if (isset($this->listeners[$event][$hash])) {
                unset($this->listeners[$event][$hash]);
            }

            if (isset($this->methods[$event][$hash])) {
                unset($this->methods[$event][$hash]);
            }
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
        $this->initialized[$eventName] = true;
        foreach ($this->listeners[$eventName] as $hash => $listener) {
            if (\is_string($listener)) {
                $this->listeners[$eventName][$hash] = $listener = $this->container->get($listener);

                $this->methods[$eventName][$hash] = $this->getMethod($listener, $eventName);
            }
        }
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
            if (\is_string($listener)) {
                $listener = $this->container->get($listener);
            }
            // throw new \InvalidArgumentException(sprintf('Using Doctrine subscriber "%s" is not allowed, declare it as a listener instead.', \is_object($listener) ? $listener::class : $listener));
            trigger_deprecation('symfony/doctrine-bridge', '6.3', 'Using Doctrine subscribers as services is deprecated, declare listeners instead');
            parent::addEventSubscriber($listener);
        }
    }

    private function getHash(string|object $listener): string
    {
        if (\is_string($listener)) {
            return '_service_'.$listener;
        }

        return spl_object_hash($listener);
    }

    private function getMethod(object $listener, string $event): string
    {
        if (!method_exists($listener, $event) && method_exists($listener, '__invoke')) {
            return '__invoke';
        }

        return $event;
    }
}

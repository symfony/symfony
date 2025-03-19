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

use Symfony\Contracts\Service\ResetInterface;

/**
 * Resetable event dispatcher.
 *
 * @author Alexander Dmitryuk <sasha_dmitruk@mail.ru>
 */
class ResetableEventDispatcher implements EventDispatcherInterface, ResetInterface
{
    private array $runtimeListeners = [];
    private array $runtimeSubscribers = [];

    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        return $this->dispatcher->dispatch($event, $eventName);
    }

    public function addListener(string $eventName, callable|array $listener, int $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
        $this->runtimeListeners[$eventName][] = $listener;
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
        $this->runtimeSubscribers[] = $subscriber;
    }

    public function removeListener(string $eventName, callable|array $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable|array $listener): ?int
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    public function reset(): void
    {
        foreach ($this->runtimeListeners as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                $this->dispatcher->removeListener($eventName, $listener);
            }
        }

        foreach ($this->runtimeSubscribers as $subscriber) {
            $this->dispatcher->removeSubscriber($subscriber);
        }
    }
}

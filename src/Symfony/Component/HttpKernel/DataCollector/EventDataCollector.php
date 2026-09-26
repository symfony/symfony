<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see TraceableEventDispatcher
 *
 * @final
 */
class EventDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /** @var iterable<EventDispatcherInterface> */
    private iterable $dispatchers;
    private ?Request $currentRequest = null;
    // the default value is used by collectors unserialized from a profile, since only their data is serialized
    private string $defaultDispatcher = 'event_dispatcher';

    /**
     * @param iterable<EventDispatcherInterface>|EventDispatcherInterface|null $dispatchers
     */
    public function __construct(
        iterable|EventDispatcherInterface|null $dispatchers = null,
        private ?RequestStack $requestStack = null,
        string $defaultDispatcher = 'event_dispatcher',
    ) {
        $this->defaultDispatcher = $defaultDispatcher;
        if ($dispatchers instanceof EventDispatcherInterface) {
            $dispatchers = [$this->defaultDispatcher => $dispatchers];
        }
        $this->dispatchers = $dispatchers ?? [];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->currentRequest = $this->requestStack && $this->requestStack->getMainRequest() !== $request ? $request : null;
        $this->data = [];
    }

    public function reset(): void
    {
        parent::reset();

        foreach ($this->dispatchers as $dispatcher) {
            if ($dispatcher instanceof ResetInterface) {
                $dispatcher->reset();
            }
        }
    }

    public function lateCollect(): void
    {
        foreach ($this->dispatchers as $name => $dispatcher) {
            if (!$dispatcher instanceof TraceableEventDispatcher) {
                continue;
            }

            $this->setCalledListeners(self::removeStubs($dispatcher->getCalledListeners($this->currentRequest)), $name);
            $this->setNotCalledListeners(self::removeStubs($dispatcher->getNotCalledListeners($this->currentRequest)), $name);
            $this->setOrphanedEvents($dispatcher->getOrphanedEvents($this->currentRequest), $name);
        }
    }

    public function getData(): array|Data
    {
        if (\is_array($this->data)) {
            foreach ($this->data as $name => $dispatcherData) {
                foreach (['called_listeners', 'not_called_listeners'] as $key) {
                    foreach ($dispatcherData[$key] ?? [] as $i => $listener) {
                        $this->data[$name][$key][$i]['stub'] ??= new ClassStub($listener['pretty'].'()', $listener['callable'] ?? null);
                    }
                }
            }

            $this->data = $this->cloneVar($this->data);
        }

        return $this->data;
    }

    /**
     * @see TraceableEventDispatcher
     */
    public function setCalledListeners(array $listeners, ?string $dispatcher = null): void
    {
        $this->data[$dispatcher ?? $this->defaultDispatcher]['called_listeners'] = $listeners;
    }

    /**
     * @see TraceableEventDispatcher
     */
    public function getCalledListeners(?string $dispatcher = null): array|Data
    {
        return $this->getData()[$dispatcher ?? $this->defaultDispatcher]['called_listeners'] ?? [];
    }

    /**
     * @see TraceableEventDispatcher
     */
    public function setNotCalledListeners(array $listeners, ?string $dispatcher = null): void
    {
        $this->data[$dispatcher ?? $this->defaultDispatcher]['not_called_listeners'] = $listeners;
    }

    /**
     * @see TraceableEventDispatcher
     */
    public function getNotCalledListeners(?string $dispatcher = null): array|Data
    {
        return $this->getData()[$dispatcher ?? $this->defaultDispatcher]['not_called_listeners'] ?? [];
    }

    /**
     * @param array $events An array of orphaned events
     *
     * @see TraceableEventDispatcher
     */
    public function setOrphanedEvents(array $events, ?string $dispatcher = null): void
    {
        $this->data[$dispatcher ?? $this->defaultDispatcher]['orphaned_events'] = $events;
    }

    /**
     * @see TraceableEventDispatcher
     */
    public function getOrphanedEvents(?string $dispatcher = null): array|Data
    {
        return $this->getData()[$dispatcher ?? $this->defaultDispatcher]['orphaned_events'] ?? [];
    }

    public function getName(): string
    {
        return 'events';
    }

    /**
     * Stubs are built back from the callables when the data is read, so that collecting doesn't load the classes of the listeners.
     */
    private static function removeStubs(array $listeners): array
    {
        foreach ($listeners as $i => $listener) {
            if (isset($listener['callable'])) {
                unset($listeners[$i]['stub']);
            }
        }

        return $listeners;
    }
}

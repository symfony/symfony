<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Debug;

use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class WrappedListener extends AbstractNamedListener
{
    private string|array|object $listener;
    private ?\Closure $optimizedListener;
    private bool $called = false;
    private bool $stoppedPropagation = false;
    private Stopwatch $stopwatch;
    private ?EventDispatcherInterface $dispatcher;
    private ClassStub|string $stub;
    private ?int $priority = null;
    private static bool $hasClassStub;

    public function __construct(callable|array $listener, ?string $name, Stopwatch $stopwatch, EventDispatcherInterface $dispatcher = null, int $priority = null)
    {
        $this->listener = $listener;
        $this->optimizedListener = $listener instanceof \Closure ? $listener : (\is_callable($listener) ? $listener(...) : null);
        $this->stopwatch = $stopwatch;
        $this->dispatcher = $dispatcher;
        $this->priority = $priority;

        parent::__construct($listener, $name);

        self::$hasClassStub ??= class_exists(ClassStub::class);
    }

    public function getWrappedListener(): callable|array
    {
        return $this->listener;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }

    public function stoppedPropagation(): bool
    {
        return $this->stoppedPropagation;
    }

    public function getInfo(string $eventName): array
    {
        $this->stub ??= self::$hasClassStub ? new ClassStub($this->pretty.'()', $this->callableRef ?? $this->listener) : $this->pretty.'()';

        return [
            'event' => $eventName,
            'priority' => $this->priority ??= $this->dispatcher?->getListenerPriority($eventName, $this->listener),
            'pretty' => $this->pretty,
            'stub' => $this->stub,
        ];
    }

    public function __invoke(object $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $dispatcher = $this->dispatcher ?: $dispatcher;

        $this->called = true;
        $this->priority ??= $dispatcher->getListenerPriority($eventName, $this->listener);

        $e = $this->stopwatch->start($this->name, 'event_listener');

        ($this->optimizedListener ?? $this->listener)($event, $eventName, $dispatcher);

        if ($e->isStarted()) {
            $e->stop();
        }

        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            $this->stoppedPropagation = true;
        }
    }
}

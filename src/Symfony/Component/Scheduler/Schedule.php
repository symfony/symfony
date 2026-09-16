<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class Schedule implements ScheduleProviderInterface
{
    /** @var array<string,RecurringMessage> */
    private array $messages = [];
    private ?LockInterface $lock = null;
    private ?CacheInterface $state = null;
    private bool $shouldRestart = false;
    private bool $onlyLastMissed = false;
    private ?EventDispatcher $listeners = null;

    /**
     * @param EventDispatcherInterface|null $dispatcher Passing a dispatcher is deprecated since Symfony 8.2
     */
    public function __construct(?EventDispatcherInterface $dispatcher = null)
    {
        if (null !== $dispatcher) {
            trigger_deprecation('symfony/scheduler', '8.2', 'Passing an event dispatcher to "%s()" is deprecated, the argument will be removed in 9.0.', __METHOD__);
        }
    }

    public function __clone()
    {
        if (null !== $this->listeners) {
            $this->listeners = clone $this->listeners;
        }
    }

    /**
     * @deprecated since Symfony 8.2, clone the schedule or use "add()" on a new Schedule instead
     */
    public function with(RecurringMessage $message, RecurringMessage ...$messages): static
    {
        trigger_deprecation('symfony/scheduler', '8.2', 'The "%s()" method is deprecated and will be removed in 9.0, clone the schedule or use "add()" on a new "%s" instead.', __METHOD__, self::class);

        return static::doAdd(new self(), $message, ...$messages);
    }

    /**
     * @return $this
     */
    public function add(RecurringMessage $message, RecurringMessage ...$messages): static
    {
        $this->setRestart(true);

        return static::doAdd($this, $message, ...$messages);
    }

    private static function doAdd(self $schedule, RecurringMessage $message, RecurringMessage ...$messages): static
    {
        foreach ([$message, ...$messages] as $m) {
            if (isset($schedule->messages[$m->getId()])) {
                throw new LogicException('Duplicated schedule message.');
            }

            $schedule->messages[$m->getId()] = $m;
        }

        return $schedule;
    }

    /**
     * @return $this
     */
    public function remove(RecurringMessage $message): static
    {
        unset($this->messages[$message->getId()]);
        $this->setRestart(true);

        return $this;
    }

    /**
     * @return $this
     */
    public function removeById(string $id): static
    {
        unset($this->messages[$id]);
        $this->setRestart(true);

        return $this;
    }

    /**
     * @return $this
     */
    public function clear(): static
    {
        $this->messages = [];
        $this->setRestart(true);

        return $this;
    }

    /**
     * @return $this
     */
    public function lock(LockInterface $lock): static
    {
        $this->lock = $lock;

        return $this;
    }

    public function getLock(): ?LockInterface
    {
        return $this->lock;
    }

    /**
     * @return $this
     */
    public function stateful(CacheInterface $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getState(): ?CacheInterface
    {
        return $this->state;
    }

    /**
     * @return $this
     */
    public function processOnlyLastMissedRun(bool $onlyLastMissed): static
    {
        $this->onlyLastMissed = $onlyLastMissed;

        return $this;
    }

    public function shouldProcessOnlyLastMissedRun(): bool
    {
        return $this->onlyLastMissed;
    }

    /**
     * @return array<RecurringMessage>
     */
    public function getRecurringMessages(): array
    {
        return array_values($this->messages);
    }

    /**
     * @return $this
     */
    public function getSchedule(): static
    {
        return $this;
    }

    public function before(callable $listener, int $priority = 0): static
    {
        ($this->listeners ??= new EventDispatcher())->addListener(PreRunEvent::class, $listener, $priority);

        return $this;
    }

    public function after(callable $listener, int $priority = 0): static
    {
        ($this->listeners ??= new EventDispatcher())->addListener(PostRunEvent::class, $listener, $priority);

        return $this;
    }

    public function onFailure(callable $listener, int $priority = 0): static
    {
        ($this->listeners ??= new EventDispatcher())->addListener(FailureEvent::class, $listener, $priority);

        return $this;
    }

    /**
     * Returns the dispatcher holding the listeners registered on this schedule, if any.
     *
     * @internal
     */
    public function getEventDispatcher(): ?EventDispatcherInterface
    {
        return $this->listeners;
    }

    public function shouldRestart(): bool
    {
        return $this->shouldRestart;
    }

    public function setRestart(bool $shouldRestart): bool
    {
        return $this->shouldRestart = $shouldRestart;
    }
}

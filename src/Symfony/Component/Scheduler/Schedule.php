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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Contracts\Cache\CacheInterface;

final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /** @var array<string,RecurringMessage> */
    private array $messages = [];
    private ?LockInterface $lock = null;
    private ?CacheInterface $state = null;
    private bool $shouldRestart = false;

    public function with(RecurringMessage $message, RecurringMessage ...$messages): static
    {
        return static::doAdd(new self($this->dispatcher), $message, ...$messages);
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
        if (!$this->dispatcher) {
            throw new LogicException(\sprintf('To register a listener with "%s()", you need to set an event dispatcher on the Schedule.', __METHOD__));
        }

        $this->dispatcher->addListener(PreRunEvent::class, $listener, $priority);

        return $this;
    }

    public function after(callable $listener, int $priority = 0): static
    {
        if (!$this->dispatcher) {
            throw new LogicException(\sprintf('To register a listener with "%s()", you need to set an event dispatcher on the Schedule.', __METHOD__));
        }

        $this->dispatcher->addListener(PostRunEvent::class, $listener, $priority);

        return $this;
    }

    public function onFailure(callable $listener, int $priority = 0): static
    {
        if (!$this->dispatcher) {
            throw new LogicException(\sprintf('To register a listener with "%s()", you need to set an event dispatcher on the Schedule.', __METHOD__));
        }

        $this->dispatcher->addListener(FailureEvent::class, $listener, $priority);

        return $this;
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

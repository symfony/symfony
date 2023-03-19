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

use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @experimental
 */
final class Schedule implements ScheduleProviderInterface
{
    /** @var array<RecurringMessage> */
    private array $messages = [];
    private ?LockInterface $lock = null;
    private ?CacheInterface $state = null;

    /**
     * @return $this
     */
    public function add(RecurringMessage $message, RecurringMessage ...$messages): static
    {
        $this->messages[] = $message;
        $this->messages = array_merge($this->messages, $messages);

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
        return $this->messages;
    }

    /**
     * @return $this
     */
    public function getSchedule(): static
    {
        return $this;
    }
}

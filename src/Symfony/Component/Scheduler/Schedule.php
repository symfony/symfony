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
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @experimental
 */
final class Schedule implements ScheduleProviderInterface
{
    /** @var array<string,RecurringMessage> */
    private array $messages = [];
    private ?LockInterface $lock = null;
    private ?CacheInterface $state = null;

    /**
     * @return $this
     */
    public function add(RecurringMessage $message, RecurringMessage ...$messages): static
    {
        foreach ([$message, ...$messages] as $m) {
            if (isset($this->messages[$m->getId()])) {
                throw new LogicException('Duplicated schedule message.');
            }

            $this->messages[$m->getId()] = $m;
        }

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
}

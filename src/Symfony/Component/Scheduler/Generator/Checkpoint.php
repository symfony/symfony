<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Generator;

use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @experimental
 */
final class Checkpoint implements CheckpointInterface
{
    private \DateTimeImmutable $time;
    private int $index = -1;
    private bool $reset = false;

    public function __construct(
        private readonly string $name,
        private readonly ?LockInterface $lock = null,
        private readonly ?CacheInterface $cache = null,
    ) {
    }

    public function acquire(\DateTimeImmutable $now): bool
    {
        if ($this->lock && !$this->lock->acquire()) {
            // Reset local state if a Lock is acquired by another Worker.
            $this->reset = true;

            return false;
        }

        if ($this->reset) {
            $this->reset = false;
            $this->save($now, -1);
        }

        $this->time ??= $now;
        if ($this->cache) {
            $this->save(...$this->cache->get($this->name, fn () => [$now, -1]));
        }

        return true;
    }

    public function time(): \DateTimeImmutable
    {
        return $this->time;
    }

    public function index(): int
    {
        return $this->index;
    }

    public function save(\DateTimeImmutable $time, int $index): void
    {
        $this->time = $time;
        $this->index = $index;
        $this->cache?->get($this->name, fn () => [$time, $index], \INF);
    }

    /**
     * Releases State, not Lock.
     *
     * It tries to keep a Lock as long as a Worker is alive.
     */
    public function release(\DateTimeImmutable $now, ?\DateTimeImmutable $nextTime): void
    {
        if (!$this->lock) {
            return;
        }

        if (!$nextTime) {
            $this->lock->release();
        } elseif ($remaining = $this->lock->getRemainingLifetime()) {
            $this->lock->refresh((float) $nextTime->format('U.u') - (float) $now->format('U.u') + $remaining);
        }
    }
}

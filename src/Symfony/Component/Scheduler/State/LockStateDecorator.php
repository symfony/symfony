<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\State;

use Symfony\Component\Lock\LockInterface;

final class LockStateDecorator implements StateInterface
{
    private bool $reset = false;

    public function __construct(
        private readonly State $inner,
        private readonly LockInterface $lock,
    ) {
    }

    public function acquire(\DateTimeImmutable $now): bool
    {
        if (!$this->lock->acquire()) {
            // Reset local state if a `Lock` is acquired by another `Worker`.
            $this->reset = true;

            return false;
        }

        if ($this->reset) {
            $this->reset = false;
            $this->inner->save($now, -1);
        }

        return $this->inner->acquire($now);
    }

    public function time(): \DateTimeImmutable
    {
        return $this->inner->time();
    }

    public function index(): int
    {
        return $this->inner->index();
    }

    public function save(\DateTimeImmutable $time, int $index): void
    {
        $this->inner->save($time, $index);
    }

    /**
     * Releases `State`, not `Lock`.
     *
     * It tries to keep a `Lock` as long as a `Worker` is alive.
     */
    public function release(\DateTimeImmutable $now, ?\DateTimeImmutable $nextTime): void
    {
        $this->inner->release($now, $nextTime);

        if (!$nextTime) {
            $this->lock->release();
        } elseif ($remaining = $this->lock->getRemainingLifetime()) {
            $this->lock->refresh((float) $nextTime->format('U.u') - (float) $now->format('U.u') + $remaining);
        }
    }
}

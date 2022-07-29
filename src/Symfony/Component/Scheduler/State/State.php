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

final class State implements StateInterface
{
    private \DateTimeImmutable $time;
    private int $index = -1;

    public function acquire(\DateTimeImmutable $now): bool
    {
        if (!isset($this->time)) {
            $this->time = $now;
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
    }

    public function release(\DateTimeImmutable $now, ?\DateTimeImmutable $nextTime): void
    {
        // skip
    }
}

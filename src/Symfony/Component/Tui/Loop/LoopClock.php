<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Loop;

/**
 * Small monotonic-ish clock abstraction for game and animation loops.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class LoopClock
{
    private float $time;

    public function __construct(
        ?float $time = null,
    ) {
        $this->time = $time ?? hrtime(true) / 1e9;
    }

    /**
     * Advance clock state and return elapsed seconds since previous advance.
     */
    public function advance(?float $deltaTime = null): float
    {
        if (null === $deltaTime) {
            $now = hrtime(true) / 1e9;
            $elapsed = max(0.0, $now - $this->time);
            $this->time = $now;

            return $elapsed;
        }

        $elapsed = max(0.0, $deltaTime);
        $this->time += $elapsed;

        return $elapsed;
    }

    public function now(): float
    {
        return $this->time;
    }

    public function reset(?float $time = null): void
    {
        $this->time = $time ?? hrtime(true) / 1e9;
    }
}

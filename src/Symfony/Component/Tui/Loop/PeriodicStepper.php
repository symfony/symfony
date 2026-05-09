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

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Converts elapsed time into periodic fixed-step counts.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PeriodicStepper
{
    private FixedStepAccumulator $accumulator;
    private LoopClock $clock;

    public function __construct(
        private float $intervalSeconds,
        int $maxStepsPerUpdate = 8,
    ) {
        if ($intervalSeconds <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Interval must be greater than 0, got %d.', $intervalSeconds));
        }

        $this->accumulator = new FixedStepAccumulator(1.0 / $intervalSeconds, $maxStepsPerUpdate);
        $this->clock = new LoopClock();
    }

    public static function everyMs(int $intervalMs, int $maxStepsPerUpdate = 8): self
    {
        if ($intervalMs <= 0) {
            throw new InvalidArgumentException(\sprintf('Interval must be greater than 0, got %d.', $intervalMs));
        }

        return new self($intervalMs / 1000, $maxStepsPerUpdate);
    }

    public function advance(?float $deltaTime = null): int
    {
        return $this->accumulator->computeSteps($this->clock->advance($deltaTime));
    }

    public function reset(): void
    {
        $this->accumulator->reset();
        $this->clock->reset();
    }

    public function setIntervalSeconds(float $intervalSeconds): void
    {
        if ($intervalSeconds <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Interval must be greater than 0, got %d.', $intervalSeconds));
        }

        $this->intervalSeconds = $intervalSeconds;
        $this->accumulator->setStepsPerSecond(1.0 / $intervalSeconds);
        $this->reset();
    }

    public function setIntervalMs(int $intervalMs): void
    {
        if ($intervalMs <= 0) {
            throw new InvalidArgumentException(\sprintf('Interval must be greater than 0, got %d.', $intervalMs));
        }

        $this->setIntervalSeconds($intervalMs / 1000);
    }

    public function getIntervalSeconds(): float
    {
        return $this->intervalSeconds;
    }
}

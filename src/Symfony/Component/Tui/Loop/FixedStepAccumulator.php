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
 * Converts elapsed time into bounded fixed-step counts.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class FixedStepAccumulator
{
    private float $accumulator = 0.0;

    public function __construct(
        private float $stepsPerSecond,
        private int $maxStepsPerUpdate = 5,
    ) {
        if ($stepsPerSecond <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Steps per second must be greater than 0, got %d.', $stepsPerSecond));
        }

        if ($maxStepsPerUpdate < 1) {
            throw new InvalidArgumentException(\sprintf('Max steps per update must be greater than 0, got %d.', $maxStepsPerUpdate));
        }
    }

    /**
     * @return int Number of fixed logic steps to execute for this update
     */
    public function computeSteps(float $deltaTime): int
    {
        $this->accumulator += max(0.0, $deltaTime) * $this->stepsPerSecond;

        if (0 < $steps = min($this->maxStepsPerUpdate, (int) floor($this->accumulator))) {
            $this->accumulator -= $steps;
        }

        return $steps;
    }

    public function setStepsPerSecond(float $stepsPerSecond): void
    {
        if ($stepsPerSecond <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Steps per second must be greater than 0, got %d.', $stepsPerSecond));
        }

        $this->stepsPerSecond = $stepsPerSecond;
    }

    public function reset(): void
    {
        $this->accumulator = 0.0;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\MemoryUsageCalculator;

trait LRUMemoryTracker
{
    private const float DEFAULT_FACTOR = 1.8;

    private float $factor = self::DEFAULT_FACTOR;

    private int $estimatedMemory = 0;

    private int $counter = 0;

    public function estimateMemorySize(mixed $value): int
    {
        $size = match (true) {
            \is_int($value), \is_float($value) => 8,
            \is_bool($value) => 1,
            null === $value => 0,
            \is_string($value) => \strlen($value),
            default => \strlen(serialize($value)),
        };

        return (int) ($size * $this->factor);
    }

    public function releaseMemorySize(int $size): void
    {
        $this->estimatedMemory -= $size;
        $this->estimatedMemory = max(0, $this->estimatedMemory);
    }

    public function allocateMemorySize(int $size): void
    {
        $this->estimatedMemory += $size;
    }

    public function tick(MemoryUsageCalculator $memoryUsageCalculator): void
    {
        if (0 !== ++$this->counter % 100) {
            return;
        }
        if (0 === $this->estimatedMemory) {
            return;
        }

        $real = $memoryUsageCalculator->calculate();
        $ratio = $real / $this->estimatedMemory;

        $this->factor = $this->factor * 0.9 + $ratio * 0.1;
        $this->factor = max(1.2, min(3.0, $this->factor));
    }

    public function restartTracking(): void
    {
        $this->counter = 0;
        $this->estimatedMemory = 0;
        $this->factor = self::DEFAULT_FACTOR;
    }
}

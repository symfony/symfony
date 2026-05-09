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
 * Internal scheduler for repeat callbacks executed from the TUI tick.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TickScheduler
{
    private int $counter = 0;

    /**
     * @var array<string, array{
     *     callback: callable(): void,
     *     interval: float,
     *     next_run_at: float
     * }>
     */
    private array $intervals = [];

    /**
     * @param callable(): void $callback
     */
    public function schedule(callable $callback, float $intervalSeconds): string
    {
        if ($intervalSeconds <= 0) {
            throw new InvalidArgumentException(\sprintf('Interval must be greater than 0, got %f.', $intervalSeconds));
        }

        $id = 'interval-'.(++$this->counter);
        $this->intervals[$id] = [
            'callback' => $callback,
            'interval' => $intervalSeconds,
            'next_run_at' => microtime(true) + $intervalSeconds,
        ];

        return $id;
    }

    public function cancel(string $id): void
    {
        unset($this->intervals[$id]);
    }

    public function clear(): void
    {
        $this->intervals = [];
    }

    public function runDue(?float $now = null): void
    {
        if (!$this->intervals) {
            return;
        }

        $now ??= microtime(true);
        $intervals = $this->intervals;

        foreach ($intervals as $id => $interval) {
            if (!isset($this->intervals[$id])) {
                continue;
            }

            if ($interval['next_run_at'] > $now) {
                continue;
            }

            $this->intervals[$id]['next_run_at'] = $now + $interval['interval'];
            ($interval['callback'])();
        }
    }

    public function getNextDelay(?float $now = null): ?float
    {
        if (!$this->intervals) {
            return null;
        }

        $now ??= microtime(true);
        $nextAt = null;

        foreach ($this->intervals as $interval) {
            $nextAt = null === $nextAt ? $interval['next_run_at'] : min($nextAt, $interval['next_run_at']);
        }

        return max(0.001, $nextAt - $now);
    }
}

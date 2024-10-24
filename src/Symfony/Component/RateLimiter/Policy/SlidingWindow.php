<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Policy;

use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\ClockTrait;
use Symfony\Component\RateLimiter\Exception\InvalidIntervalException;
use Symfony\Component\RateLimiter\LimiterStateInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @internal
 */
final class SlidingWindow implements LimiterStateInterface
{
    use ClockTrait;

    private int $hitCount = 0;
    private int $hitCountForLastWindow = 0;
    private float $windowEndAt;

    public function __construct(
        private string $id,
        private int $intervalInSeconds,
        ?ClockInterface $clock = null,
    ) {
        if ($intervalInSeconds < 1) {
            throw new InvalidIntervalException(\sprintf('The interval must be positive integer, "%d" given.', $intervalInSeconds));
        }
        $this->setClock($clock);
        $this->windowEndAt = $this->now() + $intervalInSeconds;
    }

    public static function createFromPreviousWindow(self $window, int $intervalInSeconds, ?ClockInterface $clock = null): self
    {
        $new = new self($window->id, $intervalInSeconds);
        $windowEndAt = $window->windowEndAt + $intervalInSeconds;

        if (($clock?->now()->format('U.u') ?? microtime(true)) < $windowEndAt) {
            $new->hitCountForLastWindow = $window->hitCount;
            $new->windowEndAt = $windowEndAt;
        }

        return $new;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the remaining of this timeframe and the next one.
     */
    public function getExpirationTime(): int
    {
        return (int) ($this->windowEndAt + $this->intervalInSeconds - $this->now());
    }

    public function isExpired(): bool
    {
        return $this->now() > $this->windowEndAt;
    }

    public function add(int $hits = 1): void
    {
        $this->hitCount += $hits;
    }

    /**
     * Calculates the sliding window number of request.
     */
    public function getHitCount(): int
    {
        $startOfWindow = $this->windowEndAt - $this->intervalInSeconds;
        $percentOfCurrentTimeFrame = min(($this->now() - $startOfWindow) / $this->intervalInSeconds, 1);

        return (int) floor($this->hitCountForLastWindow * (1 - $percentOfCurrentTimeFrame) + $this->hitCount);
    }

    public function calculateTimeForTokens(int $maxSize, int $tokens): float
    {
        $remaining = $maxSize - $this->getHitCount();
        if ($remaining >= $tokens) {
            return 0;
        }

        $time = $this->now();
        $startOfWindow = $this->windowEndAt - $this->intervalInSeconds;
        $timePassed = $time - $startOfWindow;
        $windowPassed = min($timePassed / $this->intervalInSeconds, 1);
        $releasable = max(1, $maxSize - floor($this->hitCountForLastWindow * (1 - $windowPassed)));
        $remainingWindow = $this->intervalInSeconds - $timePassed;
        $needed = $tokens - $remaining;

        if ($releasable >= $needed) {
            return $needed * ($remainingWindow / max(1, $releasable));
        }

        return ($this->windowEndAt - $time) + ($needed - $releasable) * ($this->intervalInSeconds / $maxSize);
    }

    public function __serialize(): array
    {
        return [
            pack('NNN', $this->hitCount, $this->hitCountForLastWindow, $this->intervalInSeconds).$this->id => $this->windowEndAt,
        ];
    }

    public function __unserialize(array $data): void
    {
        // BC layer for old objects serialized via __sleep
        if (5 === \count($data)) {
            $data = array_values($data);
            $this->id = $data[0];
            $this->hitCount = $data[1];
            $this->intervalInSeconds = $data[2];
            $this->hitCountForLastWindow = $data[3];
            $this->windowEndAt = $data[4];

            return;
        }

        $pack = key($data);
        $this->windowEndAt = $data[$pack];
        ['a' => $this->hitCount, 'b' => $this->hitCountForLastWindow, 'c' => $this->intervalInSeconds] = unpack('Na/Nb/Nc', $pack);
        $this->id = substr($pack, 12);
    }
}

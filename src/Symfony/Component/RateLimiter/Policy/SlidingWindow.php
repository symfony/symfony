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

use Symfony\Component\RateLimiter\Exception\InvalidIntervalException;
use Symfony\Component\RateLimiter\LimiterStateInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @internal
 * @experimental in 5.3
 */
final class SlidingWindow implements LimiterStateInterface
{
    private $id;

    /**
     * @var int
     */
    private $hitCount = 0;

    /**
     * @var int
     */
    private $hitCountForLastWindow = 0;

    /**
     * @var int how long a time frame is
     */
    private $intervalInSeconds;

    /**
     * @var int the unix timestamp when the current window ends
     */
    private $windowEndAt;

    /**
     * @var bool true if this window has been cached
     */
    private $cached = true;

    public function __construct(string $id, int $intervalInSeconds)
    {
        if ($intervalInSeconds < 1) {
            throw new InvalidIntervalException(sprintf('The interval must be positive integer, "%d" given.', $intervalInSeconds));
        }
        $this->id = $id;
        $this->intervalInSeconds = $intervalInSeconds;
        $this->windowEndAt = time() + $intervalInSeconds;
        $this->cached = false;
    }

    public static function createFromPreviousWindow(self $window, int $intervalInSeconds): self
    {
        $new = new self($window->id, $intervalInSeconds);
        $new->hitCountForLastWindow = $window->hitCount;
        $new->windowEndAt = $window->windowEndAt + $intervalInSeconds;

        return $new;
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        // $cached is not serialized, it should only be set
        // upon first creation of the window.
        return ['id', 'hitCount', 'intervalInSeconds', 'hitCountForLastWindow', 'windowEndAt'];
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Store for the rest of this time frame and next.
     */
    public function getExpirationTime(): ?int
    {
        if ($this->cached) {
            return null;
        }

        return 2 * $this->intervalInSeconds;
    }

    public function isExpired(): bool
    {
        return time() > $this->windowEndAt;
    }

    public function add(int $hits = 1)
    {
        $this->hitCount += $hits;
    }

    /**
     * Calculates the sliding window number of request.
     */
    public function getHitCount(): int
    {
        $startOfWindow = $this->windowEndAt - $this->intervalInSeconds;
        $percentOfCurrentTimeFrame = (time() - $startOfWindow) / $this->intervalInSeconds;

        return (int) floor($this->hitCountForLastWindow * (1 - $percentOfCurrentTimeFrame) + $this->hitCount);
    }

    public function getRetryAfter(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('U', $this->windowEndAt);
    }
}

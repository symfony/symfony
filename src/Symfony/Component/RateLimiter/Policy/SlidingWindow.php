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
     * @var float the unix timestamp when the current window ends
     */
    private $windowEndAt;

    public function __construct(string $id, int $intervalInSeconds)
    {
        if ($intervalInSeconds < 1) {
            throw new InvalidIntervalException(sprintf('The interval must be positive integer, "%d" given.', $intervalInSeconds));
        }
        $this->id = $id;
        $this->intervalInSeconds = $intervalInSeconds;
        $this->windowEndAt = microtime(true) + $intervalInSeconds;
    }

    public static function createFromPreviousWindow(self $window, int $intervalInSeconds): self
    {
        $new = new self($window->id, $intervalInSeconds);
        $windowEndAt = $window->windowEndAt + $intervalInSeconds;

        if (microtime(true) < $windowEndAt) {
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
        return (int) ($this->windowEndAt + $this->intervalInSeconds - microtime(true));
    }

    public function isExpired(): bool
    {
        return microtime(true) > $this->windowEndAt;
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
        $percentOfCurrentTimeFrame = min((microtime(true) - $startOfWindow) / $this->intervalInSeconds, 1);

        return (int) floor($this->hitCountForLastWindow * (1 - $percentOfCurrentTimeFrame) + $this->hitCount);
    }

    public function getRetryAfter(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $this->windowEndAt));
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

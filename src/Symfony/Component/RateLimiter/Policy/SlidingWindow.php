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
    private string $id;
    private int $hitCount = 0;
    private int $hitCountForLastWindow = 0;
    private int $intervalInSeconds;
    private float $windowEndAt;
    private ?int $maxSize = null;

    public function __construct(string $id, int $intervalInSeconds)
    {
        if ($intervalInSeconds < 1) {
            throw new InvalidIntervalException(\sprintf('The interval must be positive integer, "%d" given.', $intervalInSeconds));
        }
        $this->id = $id;
        $this->intervalInSeconds = $intervalInSeconds;
        $this->windowEndAt = microtime(true) + $intervalInSeconds;
    }

    public static function createFromPreviousWindow(self $window, int $intervalInSeconds): self
    {
        $new = new self($window->id, $intervalInSeconds);
        $new->maxSize = $window->maxSize;
        // without a size, no hits are carried to the next windows
        $maxSize = $window->maxSize ?? max(1, $window->hitCount);
        $windows = max(1, 1 + (int) ((microtime(true) - $window->windowEndAt) / $intervalInSeconds));
        // the reserved hits beyond the size of a window act in the next ones
        $hitCountForLastWindow = min($maxSize, max(0, $window->hitCount - ($windows - 1) * $maxSize));

        if (1 === $windows || $hitCountForLastWindow) {
            $new->hitCountForLastWindow = $hitCountForLastWindow;
            $new->hitCount = max(0, $window->hitCount - $windows * $maxSize);
            $new->windowEndAt = $window->windowEndAt + $windows * $intervalInSeconds;
        }

        return $new;
    }

    public function configure(int $maxSize): void
    {
        if (null !== $this->maxSize && $maxSize !== $this->maxSize && $this->hitCount > 0) {
            // full windows stay full and the last one is capped at the new size, like for fixed windows
            $fullWindows = intdiv($this->hitCount - 1, $this->maxSize);
            $this->hitCount = $fullWindows * $maxSize + min($this->hitCount - $fullWindows * $this->maxSize, $maxSize);
        }

        $this->maxSize = $maxSize;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the remaining of this timeframe and of the next ones its hits act in.
     */
    public function getExpirationTime(): int
    {
        $windows = null !== $this->maxSize ? max(1, (int) ceil($this->hitCount / $this->maxSize)) : 1;

        return (int) ceil($this->windowEndAt + $windows * $this->intervalInSeconds - microtime(true));
    }

    public function isExpired(): bool
    {
        return microtime(true) > $this->windowEndAt;
    }

    public function add(int $hits = 1): void
    {
        $this->hitCount = max(0, $this->hitCount + $hits);
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

    /**
     * @deprecated since Symfony 6.4, use {@see self::calculateTimeForTokens} instead
     */
    public function getRetryAfter(): \DateTimeImmutable
    {
        trigger_deprecation('symfony/ratelimiter', '6.4', 'The "%s()" method is deprecated, use "%s::calculateTimeForTokens" instead.', __METHOD__, self::class);

        return \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', microtime(true) + $this->calculateTimeForTokens(max(1, $this->getHitCount()), 1)));
    }

    public function calculateTimeForTokens(int $maxSize, int $tokens): float
    {
        $remaining = $maxSize - $this->getHitCount();
        if ($remaining >= $tokens) {
            return 0;
        }

        $time = microtime(true);
        $windowPassed = min(($time - ($this->windowEndAt - $this->intervalInSeconds)) / $this->intervalInSeconds, 1);
        // Hits of the previous window fade out during the current one,
        // while hits of the current window count in full until it ends and only fade out during the next one
        $releasableInWindow = $this->hitCountForLastWindow * (1 - $windowPassed);
        // getHitCount() floors the weighted count: the tokens are available once it goes below this threshold
        $toRelease = $releasableInWindow + $this->hitCount - ($maxSize - $tokens + 1);

        if ($toRelease < $releasableInWindow) {
            return $toRelease * $this->intervalInSeconds / $this->hitCountForLastWindow;
        }

        // the reserved hits beyond the size of a window act in the next ones: the tokens fit in the first window with room left
        $inWindow = (int) ceil(($this->hitCount + $tokens) / $maxSize) - 1;
        $hitCountForPreviousWindow = min($maxSize, $this->hitCount - ($inWindow - 1) * $maxSize);
        $hitCountForWindow = max(0, $this->hitCount - $inWindow * $maxSize);
        $toReleaseInWindow = $hitCountForPreviousWindow + $hitCountForWindow - ($maxSize - $tokens + 1);

        return ($this->windowEndAt - $time) + ($inWindow - 1) * $this->intervalInSeconds + $toReleaseInWindow * $this->intervalInSeconds / max(1, $hitCountForPreviousWindow);
    }

    public function __serialize(): array
    {
        $data = [
            pack('NNN', $this->hitCount, $this->hitCountForLastWindow, $this->intervalInSeconds).$this->id => $this->windowEndAt,
        ];

        // older versions read the first entry only
        if (null !== $this->maxSize) {
            $data[''] = $this->maxSize;
        }

        return $data;
    }

    public function __unserialize(array $data): void
    {
        // BC layer for old objects serialized via __sleep
        if (5 === \count($data)) {
            $data = array_values($data);
            if ($data[0] instanceof \Stringable) {
                throw new \BadMethodCallException('Cannot unserialize '.self::class);
            }
            $this->id = $data[0];
            $this->hitCount = $data[1];
            $this->intervalInSeconds = $data[2];
            $this->hitCountForLastWindow = $data[3];
            $this->windowEndAt = $data[4];

            return;
        }

        $pack = key($data);
        $this->windowEndAt = $data[$pack];
        $this->maxSize = $data[''] ?? null;
        ['a' => $this->hitCount, 'b' => $this->hitCountForLastWindow, 'c' => $this->intervalInSeconds] = unpack('Na/Nb/Nc', $pack);
        $this->id = substr($pack, 12);
    }
}

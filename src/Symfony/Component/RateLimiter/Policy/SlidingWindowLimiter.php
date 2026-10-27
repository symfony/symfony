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

use Symfony\Component\Lock\LockInterface;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use Symfony\Component\RateLimiter\Util\TimeUtil;

/**
 * The sliding window algorithm will look at your last window and the current one.
 * It is good algorithm to reduce bursts.
 *
 * Example:
 * Last time window we did 8 hits. We are currently 25% into
 * the current window. We have made 3 hits in the current window so far.
 * That means our sliding window hit count is (75% * 8) + 3 = 9.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class SlidingWindowLimiter implements LimiterInterface
{
    use ResetLimiterTrait;

    private int $interval;

    public function __construct(
        string $id,
        private int $limit,
        \DateInterval $interval,
        StorageInterface $storage,
        ?LockInterface $lock = null,
    ) {
        $this->storage = $storage;
        $this->lock = $lock;
        $this->id = $id;
        $this->interval = TimeUtil::dateIntervalToSeconds($interval);
    }

    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        if ($tokens > $this->limit) {
            throw new \InvalidArgumentException(\sprintf('Cannot reserve more tokens (%d) than the size of the rate limiter (%d).', $tokens, $this->limit));
        }

        $this->lock?->acquire(true);

        try {
            $window = $this->storage->fetch($this->id);
            if (!$window instanceof SlidingWindow) {
                $window = new SlidingWindow($this->id, $this->interval);
            } elseif ($window->isExpired()) {
                $window = SlidingWindow::createFromPreviousWindow($window, $this->interval);
            }

            $now = microtime(true);
            $hitCount = $window->getHitCount();
            $availableTokens = $this->getAvailableTokens($hitCount);
            if (0 === $tokens) {
                $resetDuration = $window->calculateTimeForTokens($this->limit, 1);
                $resetTime = \DateTimeImmutable::createFromTimestamp($availableTokens > 0 ? floor($now) : ceil($now + $resetDuration));

                return new Reservation($now, new RateLimit($availableTokens, $resetTime, true, $this->limit, $this->resetAt($window, $now), 0, null, $this->id));
            }
            if ($availableTokens >= $tokens) {
                $window->add($tokens);

                $retryAfter = $now;
                if ($availableTokens === $tokens) {
                    $retryAfter += $window->calculateTimeForTokens($this->limit, 1);
                }

                $reservation = new Reservation($now, new RateLimit($this->getAvailableTokens($window->getHitCount()), \DateTimeImmutable::createFromTimestamp($availableTokens === $tokens ? ceil($retryAfter) : floor($retryAfter)), true, $this->limit, $this->resetAt($window, $now), max(0, $tokens), $tokens > 0 ? $now : null, $this->id));
            } else {
                $waitDuration = $window->calculateTimeForTokens($this->limit, $tokens);

                // the tokens are not available yet, even when the computed wait is 0 at the end of a window
                if (null !== $maxTime && (0.0 === $maxTime || $waitDuration > $maxTime)) {
                    // process needs to wait longer than set interval
                    throw new MaxWaitDurationExceededException(\sprintf('The rate limiter wait time ("%d" seconds) is longer than the provided maximum time ("%d" seconds).', $waitDuration, $maxTime), new RateLimit($this->getAvailableTokens($window->getHitCount()), \DateTimeImmutable::createFromTimestamp(ceil($now + $waitDuration)), false, $this->limit, $this->resetAt($window, $now), 0, null, $this->id));
                }

                $window->add($tokens);

                $reservation = new Reservation($now + $waitDuration, new RateLimit($this->getAvailableTokens($window->getHitCount()), \DateTimeImmutable::createFromTimestamp(ceil($now + $waitDuration)), false, $this->limit, $this->resetAt($window, $now), 0, null, $this->id));
            }

            if (0 !== $tokens) {
                $this->storage->save($window);
            }
        } finally {
            $this->lock?->release();
        }

        return $reservation;
    }

    public function consume(int $tokens = 1): RateLimit
    {
        try {
            return $this->reserve($tokens, 0)->getRateLimit();
        } catch (MaxWaitDurationExceededException $e) {
            return $e->getRateLimit();
        }
    }

    /**
     * Gives back the tokens a consume() call took, when that call should not count.
     *
     * Hits fade out as the window slides, so refunding a hit whose weight has
     * already been released has no effect: the tokens are back already.
     */
    public function refund(RateLimit $rateLimit): RateLimit
    {
        if (!$rateLimit->isAccepted() || 0 >= $rateLimit->getConsumedTokens() || null === $rateLimit->getHitTime() || $this->id !== $rateLimit->getLimiterId()) {
            return $rateLimit;
        }

        $this->lock?->acquire(true);

        try {
            $window = $this->storage->fetch($this->id);

            if (!$window instanceof SlidingWindow) {
                return $rateLimit;
            }

            $now = microtime(true);
            $hitTime = $rateLimit->getHitTime();
            $windowEnd = $window->getSlidingWindowEndAt();
            $currentWindowStart = $windowEnd - $this->interval;
            $previousWindowStart = $currentWindowStart - $this->interval;

            if ($hitTime >= $currentWindowStart && $hitTime < $windowEnd) {
                if ($now >= $windowEnd + $this->interval) {
                    return $rateLimit;
                }

                $window->add(-$rateLimit->getConsumedTokens());
                $rateLimit->markRefunded();

                if ($now >= $windowEnd) {
                    // the window expired in the meantime, carry the remaining hits into the new one
                    $window = SlidingWindow::createFromPreviousWindow($window, $this->interval);
                }
            } elseif ($hitTime >= $previousWindowStart && $hitTime < $currentWindowStart) {
                if ($now >= $windowEnd) {
                    return $rateLimit;
                }

                $window->addForLastWindow(-$rateLimit->getConsumedTokens());
                $rateLimit->markRefunded();
            } else {
                return $rateLimit;
            }

            $this->storage->save($window);

            $availableTokens = $this->getAvailableTokens($window->getHitCount());

            return new RateLimit($availableTokens, \DateTimeImmutable::createFromTimestamp($availableTokens > 0 ? (int) floor($now) : (int) ceil($now + $window->calculateTimeForTokens($this->limit, 1))), true, $this->limit, $this->resetAt($window, $now), 0, null, $this->id);
        } finally {
            $this->lock?->release();
        }
    }

    private function getAvailableTokens(int $hitCount): int
    {
        return $this->limit - $hitCount;
    }

    private function resetAt(SlidingWindow $window, float $now): \DateTimeImmutable
    {
        if (!$window->getHitCount()) {
            return \DateTimeImmutable::createFromTimestamp((int) $now);
        }

        // the decay crosses below one hit strictly after that instant
        return \DateTimeImmutable::createFromTimestamp((int) max($now, $window->getFullCapacityTime()) + 1);
    }
}

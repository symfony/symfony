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
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class FixedWindowLimiter implements LimiterInterface
{
    use ResetLimiterTrait;

    private int $intervalInSeconds;
    private \DateInterval $interval;

    /**
     * @param \DateTimeImmutable|null $anchorAt When set, the window is aligned to a calendar starting at this datetime
     *                                          and resetting every $interval, instead of starting on the first hit.
     *                                          The anchor's timezone is preserved so period boundaries are DST-correct
     *                                          (e.g. a midnight anchor in "America/New_York" stays at local midnight
     *                                          across DST transitions). The anchor may sit in the past or in the future;
     *                                          the active period is normalized so it contains "now". $interval must be
     *                                          at least one month when $anchorAt is set; sub-month intervals throw
     *                                          \InvalidArgumentException.
     */
    public function __construct(
        string $id,
        private int $limit,
        \DateInterval $interval,
        StorageInterface $storage,
        ?LockInterface $lock = null,
        private readonly ?\DateTimeImmutable $anchorAt = null,
    ) {
        if ($limit < 1) {
            throw new \InvalidArgumentException(\sprintf('Cannot set the limit of "%s" to 0, as that would never accept any hit.', __CLASS__));
        }

        if (null !== $anchorAt && 0 === $interval->y && 0 === $interval->m) {
            throw new \InvalidArgumentException(\sprintf('The "anchorAt" option of "%s" requires an interval of at least one month.', __CLASS__));
        }

        $this->storage = $storage;
        $this->lock = $lock;
        $this->id = $id;
        $this->interval = $interval;
        $this->intervalInSeconds = TimeUtil::dateIntervalToSeconds($interval);
    }

    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        if ($tokens > $this->limit) {
            throw new \InvalidArgumentException(\sprintf('Cannot reserve more tokens (%d) than the size of the rate limiter (%d).', $tokens, $this->limit));
        }

        $this->lock?->acquire(true);

        try {
            $now = microtime(true);
            $window = $this->storage->fetch($this->id);

            if (null !== $this->anchorAt) {
                $nowDt = \DateTimeImmutable::createFromTimestamp((int) $now);
                if (!$window instanceof CalendarAlignedWindow || $nowDt < $window->getPeriodStart() || $nowDt >= $window->getPeriodEnd()) {
                    [$periodStart, $periodEnd] = $this->computePeriod($nowDt);
                    $window = new CalendarAlignedWindow($this->id, $this->limit, $periodStart, $periodEnd);
                }
            } elseif (!$window instanceof Window) {
                $window = new Window($this->id, $this->intervalInSeconds, $this->limit, $now);
            } else {
                $window->configure($this->intervalInSeconds, $this->limit);
            }

            $availableTokens = $window->getAvailableTokens($now);
            // the window rounds waits up to the second, so measuring them from the start of the current second gives the instant rounded up
            $second = floor($now);

            if (0 === $tokens) {
                $waitDuration = $window->calculateTimeForTokens(1, $now);
                $reservation = new Reservation($now + $waitDuration, new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromTimestamp($availableTokens > 0 ? $second : $second + $window->calculateTimeForTokens(1, $second)), true, $this->limit, $this->resetAt($window, $now), 0, null, $this->id));
            } elseif ($availableTokens >= $tokens) {
                $window->add($tokens, $now);

                $retryAfter = $second;
                if ($availableTokens === $tokens) {
                    $retryAfter += $window->calculateTimeForTokens(1, $second);
                }

                $reservation = new Reservation($now, new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromTimestamp($retryAfter), true, $this->limit, $this->resetAt($window, $now), max(0, $tokens), $tokens > 0 ? $now : null, $this->id));
            } else {
                $waitDuration = $window->calculateTimeForTokens($tokens, $now);
                $retryAfter = \DateTimeImmutable::createFromTimestamp($second + $window->calculateTimeForTokens($tokens, $second));

                // the tokens are not available yet, even when the computed wait is 0 at the end of a window
                if (null !== $maxTime && (0.0 === $maxTime || $waitDuration > $maxTime)) {
                    // process needs to wait longer than set interval
                    throw new MaxWaitDurationExceededException(\sprintf('The rate limiter wait time ("%d" seconds) is longer than the provided maximum time ("%d" seconds).', $waitDuration, $maxTime), new RateLimit($window->getAvailableTokens($now), $retryAfter, false, $this->limit, $this->resetAt($window, $now), 0, null, $this->id));
                }

                $window->add($tokens, $now);

                $reservation = new Reservation($now + $waitDuration, new RateLimit($window->getAvailableTokens($now), $retryAfter, false, $this->limit, $this->resetAt($window, $now), 0, null, $this->id));
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
     * The window resets on its own at the end of its interval, so refunding a hit
     * made in a previous window has no effect: the tokens are back already.
     */
    public function refund(RateLimit $rateLimit): RateLimit
    {
        if (!$rateLimit->isAccepted() || 0 >= $rateLimit->getConsumedTokens() || null === $rateLimit->getHitTime() || $this->id !== $rateLimit->getLimiterId() || $rateLimit->isRefunded()) {
            return $rateLimit;
        }

        $this->lock?->acquire(true);

        try {
            $now = microtime(true);
            $window = $this->storage->fetch($this->id);

            if (!$window instanceof Window && !$window instanceof CalendarAlignedWindow) {
                return $rateLimit;
            }

            $hitTime = $rateLimit->getHitTime();

            if ($window instanceof CalendarAlignedWindow) {
                // the hit is counted until the calendar period it belongs to ends, and the period itself tells
                $periodStart = $window->getPeriodStart()->getTimestamp() + $window->getPeriodStart()->getMicrosecond() / 1e6;
                $periodEnd = $window->getPeriodEnd()->getTimestamp() + $window->getPeriodEnd()->getMicrosecond() / 1e6;
            } else {
                // the hit is counted until the window it was made in ends, and the window start tells
                // whether the stored window is the one the hit belongs to
                $periodStart = $window->getTimer();
                $periodEnd = $window->getTimer() + $window->getIntervalInSeconds();
            }

            if (!$window->getHitCount() || $hitTime < $periodStart || $hitTime >= $periodEnd || $now >= $periodEnd) {
                return $rateLimit;
            }

            $window->add(-$rateLimit->getConsumedTokens(), $now);
            $rateLimit->markRefunded();
            $this->storage->save($window);

            return new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromTimestamp((int) ceil($now)), true, $this->limit, $this->resetAt($window, $now), 0, null, $this->id);
        } finally {
            $this->lock?->release();
        }
    }

    public function getAvailableTokens(int $hitCount): int
    {
        return $this->limit - $hitCount;
    }

    private function resetAt(Window|CalendarAlignedWindow $window, float $now): \DateTimeImmutable
    {
        if ($window->getAvailableTokens($now) >= $this->limit) {
            return \DateTimeImmutable::createFromTimestamp((int) $now);
        }

        // the window's end is fixed, so the reset must not drift as the clock advances inside it
        return \DateTimeImmutable::createFromTimestamp((int) ceil($window->calculateAvailabilityTime($this->limit, $now)));
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function computePeriod(\DateTimeImmutable $now): array
    {
        $periodStart = $this->anchorAt;
        $periodEnd = $periodStart->add($this->interval);

        if ($periodEnd <= $now) {
            do {
                $periodStart = $periodEnd;
                $periodEnd = $periodStart->add($this->interval);
            } while ($periodEnd <= $now);

            return [$periodStart, $periodEnd];
        }

        while ($periodStart > $now) {
            $periodEnd = $periodStart;
            $periodStart = $periodEnd->sub($this->interval);
        }

        return [$periodStart, $periodEnd];
    }
}

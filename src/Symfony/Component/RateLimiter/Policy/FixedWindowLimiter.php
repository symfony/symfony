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
                $nowDt = new \DateTimeImmutable(\sprintf('@%d', $now));
                if (!$window instanceof CalendarAlignedWindow || $nowDt < $window->getPeriodStart() || $nowDt >= $window->getPeriodEnd()) {
                    [$periodStart, $periodEnd] = $this->computePeriod($nowDt);
                    $window = new CalendarAlignedWindow($this->id, $this->limit, $periodStart, $periodEnd);
                }
            } elseif (!$window instanceof Window) {
                $window = new Window($this->id, $this->intervalInSeconds, $this->limit);
            }

            $availableTokens = $window->getAvailableTokens($now);

            if (0 === $tokens) {
                $waitDuration = $window->calculateTimeForTokens(1, $now);
                $reservation = new Reservation($now + $waitDuration, new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromFormat('U', floor($now + $waitDuration)), true, $this->limit));
            } elseif ($availableTokens >= $tokens) {
                $window->add($tokens, $now);

                $retryAfter = $now;
                if ($availableTokens === $tokens) {
                    $retryAfter += $window->calculateTimeForTokens(1, $now);
                }

                $reservation = new Reservation($now, new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromFormat('U', floor($retryAfter)), true, $this->limit));
            } else {
                $waitDuration = $window->calculateTimeForTokens($tokens, $now);

                if (null !== $maxTime && $waitDuration > $maxTime) {
                    // process needs to wait longer than set interval
                    throw new MaxWaitDurationExceededException(\sprintf('The rate limiter wait time ("%d" seconds) is longer than the provided maximum time ("%d" seconds).', $waitDuration, $maxTime), new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromFormat('U', floor($now + $waitDuration)), false, $this->limit));
                }

                $window->add($tokens, $now);

                $reservation = new Reservation($now + $waitDuration, new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromFormat('U', floor($now + $waitDuration)), false, $this->limit));
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

    public function getAvailableTokens(int $hitCount): int
    {
        return $this->limit - $hitCount;
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

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
                $resetDuration = $window->calculateTimeForTokens($this->limit, $window->getHitCount());
                $resetTime = \DateTimeImmutable::createFromFormat('U', $availableTokens ? floor($now) : floor($now + $resetDuration));

                return new Reservation($now, new RateLimit($availableTokens, $resetTime, true, $this->limit));
            }
            if ($availableTokens >= $tokens) {
                $window->add($tokens);

                $reservation = new Reservation($now, new RateLimit($this->getAvailableTokens($window->getHitCount()), \DateTimeImmutable::createFromFormat('U', floor($now)), true, $this->limit));
            } else {
                $waitDuration = $window->calculateTimeForTokens($this->limit, $tokens);

                if (null !== $maxTime && $waitDuration > $maxTime) {
                    // process needs to wait longer than set interval
                    throw new MaxWaitDurationExceededException(\sprintf('The rate limiter wait time ("%d" seconds) is longer than the provided maximum time ("%d" seconds).', $waitDuration, $maxTime), new RateLimit($this->getAvailableTokens($window->getHitCount()), \DateTimeImmutable::createFromFormat('U', floor($now + $waitDuration)), false, $this->limit));
                }

                $window->add($tokens);

                $reservation = new Reservation($now + $waitDuration, new RateLimit($this->getAvailableTokens($window->getHitCount()), \DateTimeImmutable::createFromFormat('U', floor($now + $waitDuration)), false, $this->limit));
            }

            if (0 < $tokens) {
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

    private function getAvailableTokens(int $hitCount): int
    {
        return $this->limit - $hitCount;
    }
}

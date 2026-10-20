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

    private int $interval;

    public function __construct(
        string $id,
        private int $limit,
        \DateInterval $interval,
        StorageInterface $storage,
        ?LockInterface $lock = null,
    ) {
        if ($limit < 1) {
            throw new \InvalidArgumentException(\sprintf('Cannot set the limit of "%s" to 0, as that would never accept any hit.', __CLASS__));
        }

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
            if (!$window instanceof Window) {
                $window = new Window($this->id, $this->interval, $this->limit);
            }

            $window->configure($this->interval, $this->limit);

            $now = microtime(true);
            $availableTokens = $window->getAvailableTokens($now);
            // the window rounds waits up to the second, so measuring them from the start of the current second gives the instant rounded up
            $second = floor($now);

            if (0 === $tokens) {
                $waitDuration = $window->calculateTimeForTokens(1, $now);
                $reservation = new Reservation($now + $waitDuration, new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromFormat('U', $availableTokens > 0 ? $second : $second + $window->calculateTimeForTokens(1, $second)), true, $this->limit));
            } elseif ($availableTokens >= $tokens) {
                $window->add($tokens, $now);

                $retryAfter = $second;
                if ($availableTokens === $tokens) {
                    $retryAfter += $window->calculateTimeForTokens(1, $second);
                }

                $reservation = new Reservation($now, new RateLimit($window->getAvailableTokens($now), \DateTimeImmutable::createFromFormat('U', $retryAfter), true, $this->limit));
            } else {
                $waitDuration = $window->calculateTimeForTokens($tokens, $now);
                $retryAfter = \DateTimeImmutable::createFromFormat('U', $second + $window->calculateTimeForTokens($tokens, $second));

                // the tokens are not available yet, even when the computed wait is 0 at the end of a window
                if (null !== $maxTime && (0.0 === $maxTime || $waitDuration > $maxTime)) {
                    // process needs to wait longer than set interval
                    throw new MaxWaitDurationExceededException(\sprintf('The rate limiter wait time ("%d" seconds) is longer than the provided maximum time ("%d" seconds).', $waitDuration, $maxTime), new RateLimit($window->getAvailableTokens($now), $retryAfter, false, $this->limit));
                }

                $window->add($tokens, $now);

                $reservation = new Reservation($now + $waitDuration, new RateLimit($window->getAvailableTokens($now), $retryAfter, false, $this->limit));
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
}

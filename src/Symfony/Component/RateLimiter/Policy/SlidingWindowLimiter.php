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
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
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

    private int $limit;
    private int $interval;

    public function __construct(string $id, int $limit, \DateInterval $interval, StorageInterface $storage, LockInterface $lock = null)
    {
        $this->storage = $storage;
        $this->lock = $lock;
        $this->id = $id;
        $this->limit = $limit;
        $this->interval = TimeUtil::dateIntervalToSeconds($interval);
    }

    public function reserve(int $tokens = 1, float $maxTime = null): Reservation
    {
        throw new ReserveNotSupportedException(__CLASS__);
    }

    public function consume(int $tokens = 1): RateLimit
    {
        $this->lock?->acquire(true);

        try {
            $window = $this->storage->fetch($this->id);
            if (!$window instanceof SlidingWindow) {
                $window = new SlidingWindow($this->id, $this->interval);
            } elseif ($window->isExpired()) {
                $window = SlidingWindow::createFromPreviousWindow($window, $this->interval);
            }

            $hitCount = $window->getHitCount();
            $availableTokens = $this->getAvailableTokens($hitCount);
            if ($availableTokens < $tokens) {
                return new RateLimit($availableTokens, $window->getRetryAfter(), false, $this->limit);
            }

            $window->add($tokens);

            if (0 < $tokens) {
                $this->storage->save($window);
            }

            return new RateLimit($this->getAvailableTokens($window->getHitCount()), $window->getRetryAfter(), true, $this->limit);
        } finally {
            $this->lock?->release();
        }
    }

    private function getAvailableTokens(int $hitCount): int
    {
        return $this->limit - $hitCount;
    }
}

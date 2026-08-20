<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RateLimiterBuilder
{
    public function __construct(
        private StorageInterface $storage,
        private ?LockFactory $lockFactory = null,
    ) {
    }

    /**
     * @param string               $id       Unique identifier for the rate limiter
     * @param int                  $limit    Maximum allowed hits for the passed interval
     * @param \DateInterval|string $interval If string, must be a number followed by "second",
     *                                       "minute", "hour", "day", "week" or "month" (or their
     *                                       plural equivalent)
     */
    public function slidingWindow(string $id, int $limit, \DateInterval|string $interval): RateLimiterFactoryInterface
    {
        return $this->factory([
            'id' => $id,
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => $interval,
        ]);
    }

    /**
     * @param string                         $id       Unique identifier for the rate limiter
     * @param int                            $limit    Maximum allowed hits for the passed interval
     * @param \DateInterval|string           $interval If string, must be a number followed by "second",
     *                                                 "minute", "hour", "day", "week" or "month" (or their
     *                                                 plural equivalent)
     * @param \DateTimeInterface|string|null $anchorAt Align the window to a calendar starting at this datetime
     *                                                 instead of the first hit; requires an "$interval" of at
     *                                                 least one month. Timezone-less strings are parsed as UTC.
     */
    public function fixedWindow(string $id, int $limit, \DateInterval|string $interval, \DateTimeInterface|string|null $anchorAt = null): RateLimiterFactoryInterface
    {
        return $this->factory([
            'id' => $id,
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => $interval,
            'anchor_at' => $anchorAt,
        ]);
    }

    /**
     * @param string               $id       Unique identifier for the rate limiter
     * @param int                  $limit    Maximum allowed hits in a burst
     * @param \DateInterval|string $interval Interval at which "$amount" tokens are added back
     * @param int                  $amount   Number of tokens added back per "$interval"
     */
    public function tokenBucket(string $id, int $limit, \DateInterval|string $interval, int $amount = 1): RateLimiterFactoryInterface
    {
        return $this->factory([
            'id' => $id,
            'policy' => 'token_bucket',
            'limit' => $limit,
            'rate' => [
                'interval' => $interval,
                'amount' => $amount,
            ],
        ]);
    }

    public function compound(RateLimiterFactoryInterface ...$factories): RateLimiterFactoryInterface
    {
        return new CompoundRateLimiterFactory($factories);
    }

    public function noop(): RateLimiterFactoryInterface
    {
        return $this->factory([
            'id' => 'noop',
            'policy' => 'no_limit',
        ]);
    }

    private function factory(array $config): RateLimiterFactory
    {
        return new RateLimiterFactory($config, $this->storage, $this->lockFactory);
    }
}

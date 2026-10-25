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

/**
 * Creates rate limiter factories on the fly, for limits that are not configured up front.
 */
interface RateLimiterBuilderInterface
{
    /**
     * @param string               $id       Unique identifier for the rate limiter
     * @param int                  $limit    Maximum allowed hits for the passed interval
     * @param \DateInterval|string $interval If string, must be a number followed by "second",
     *                                       "minute", "hour", "day", "week" or "month" (or their
     *                                       plural equivalent)
     */
    public function slidingWindow(string $id, int $limit, \DateInterval|string $interval): RateLimiterFactoryInterface;

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
    public function fixedWindow(string $id, int $limit, \DateInterval|string $interval, \DateTimeInterface|string|null $anchorAt = null): RateLimiterFactoryInterface;

    /**
     * @param string               $id       Unique identifier for the rate limiter
     * @param int                  $limit    Maximum allowed hits in a burst
     * @param \DateInterval|string $interval Interval at which "$amount" tokens are added back
     * @param int                  $amount   Number of tokens added back per "$interval"
     */
    public function tokenBucket(string $id, int $limit, \DateInterval|string $interval, int $amount = 1): RateLimiterFactoryInterface;

    public function compound(RateLimiterFactoryInterface ...$factories): RateLimiterFactoryInterface;

    public function noop(): RateLimiterFactoryInterface;
}

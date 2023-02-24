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

use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface LimiterInterface
{
    /**
     * Waits until the required number of tokens is available.
     *
     * The reserved tokens will be taken into account when calculating
     * future token consumptions. Do not use this method if you intend
     * to skip this process.
     *
     * @param int        $tokens  the number of tokens required
     * @param float|null $maxTime maximum accepted waiting time in seconds
     *
     * @throws MaxWaitDurationExceededException if $maxTime is set and the process needs to wait longer than its value (in seconds)
     * @throws ReserveNotSupportedException     if this limiter implementation doesn't support reserving tokens
     * @throws \InvalidArgumentException        if $tokens is larger than the maximum burst size
     */
    public function reserve(int $tokens = 1, float $maxTime = null): Reservation;

    /**
     * Use this method if you intend to drop if the required number
     * of tokens is unavailable.
     *
     * @param int $tokens the number of tokens required
     */
    public function consume(int $tokens = 1): RateLimit;

    /**
     * Resets the limit.
     */
    public function reset(): void;
}

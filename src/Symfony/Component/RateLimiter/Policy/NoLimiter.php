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

use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;

/**
 * Implements a non limiting limiter.
 *
 * This can be used in cases where an implementation requires a
 * limiter, but no rate limit should be enforced.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.3
 */
final class NoLimiter implements LimiterInterface
{
    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        return new Reservation(time(), new RateLimit(\INF, new \DateTimeImmutable(), true, \INF));
    }

    public function consume(int $tokens = 1): RateLimit
    {
        return new RateLimit(\INF, new \DateTimeImmutable(), true, \INF);
    }

    public function reset(): void
    {
    }
}

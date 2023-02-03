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

use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\ClockTrait;
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
 */
final class NoLimiter implements LimiterInterface
{
    use ClockTrait;

    public function __construct(?ClockInterface $clock = null)
    {
        $this->setClock($clock);
    }

    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        return new Reservation($this->now(), new RateLimit(\PHP_INT_MAX, new \DateTimeImmutable(), true, \PHP_INT_MAX, $this->clock), $this->clock);
    }

    public function consume(int $tokens = 1): RateLimit
    {
        return new RateLimit(\PHP_INT_MAX, new \DateTimeImmutable(), true, \PHP_INT_MAX, $this->clock);
    }

    public function reset(): void
    {
    }
}

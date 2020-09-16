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
 * Implements a non limiting limiter.
 *
 * This can be used in cases where an implementation requires a
 * limiter, but no rate limit should be enforced.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
final class NoLimiter implements LimiterInterface
{
    public function consume(int $tokens = 1): bool
    {
        return true;
    }

    public function reset(): void
    {
    }
}

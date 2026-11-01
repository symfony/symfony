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
 * Carries the rate limit returned by each limiter of a CompoundLimiter.
 *
 * @internal
 */
final class CompoundRateLimit extends RateLimit
{
    /**
     * @param array<array-key, RateLimit> $rateLimits
     */
    public function __construct(
        private array $rateLimits,
        RateLimit $rateLimit,
    ) {
        parent::__construct(
            $rateLimit->getRemainingTokens(),
            $rateLimit->getRetryAfter(),
            $rateLimit->isAccepted(),
            $rateLimit->getLimit(),
            $rateLimit->getResetAt(),
        );
    }

    /**
     * @return array<array-key, RateLimit>
     */
    public function getRateLimits(): array
    {
        return $this->rateLimits;
    }
}

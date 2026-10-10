<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\RateLimiter;

use Symfony\Component\RateLimiter\RateLimit;

/**
 * The result of applying a #[RateLimit] attribute to a request.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 *
 * @internal
 */
final class AppliedRateLimit
{
    public function __construct(
        public readonly RateLimit $rateLimit,
        public readonly int $tokens,
        public readonly string $limiter,
        public readonly bool $exposeHeaders,
    ) {
    }

    /**
     * The number of calls left, as opposed to the number of tokens remaining.
     */
    public function getRemainingCalls(): float
    {
        return $this->rateLimit->getRemainingTokens() / $this->tokens;
    }
}

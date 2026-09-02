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
 * The rate limit that governs the response, out of every #[RateLimit] consumed for the request,
 * along with the number of tokens its matching attribute consumes per call.
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

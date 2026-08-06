<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Event;

use Symfony\Component\RateLimiter\RateLimit;

/**
 * Dispatched when a caller rejects a hit that a rate limiter denied.
 *
 * A denied hit is not a rejection by itself. Callers decide what to do with it,
 * so this event is dispatched by them, not by the limiters.
 *
 * @author Marie Charles <marie.charles@hetic.net>
 */
final class RateLimitExceededEvent
{
    public function __construct(
        private readonly RateLimit $rateLimit,
        private readonly ?string $limiterName = null,
        private readonly ?string $key = null,
    ) {
    }

    public function getRateLimit(): RateLimit
    {
        return $this->rateLimit;
    }

    public function getLimiterName(): ?string
    {
        return $this->limiterName;
    }

    /**
     * The key that was consumed, when known. Its format is defined by the caller.
     */
    public function getKey(): ?string
    {
        return $this->key;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Attribute;

/**
 * Add the hability to rate limit a method from a controller
 *
 * @see https://symfony.com/doc/current/rate_limiter.html
 *
 * @author Raziel Rodrigues <raziel.rodrigues@outlook.pt>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final class RateLimit
{
    /**
     * @param string $limiter The name of the limiter to use
     * @param array $methods Methods to apply the rate limit
     */
    public function __construct(
        public string $limiter,
        public array $methods
    ) {}
}

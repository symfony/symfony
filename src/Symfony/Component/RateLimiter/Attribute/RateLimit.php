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
 * Rate limit the controller.
 *
 * @see https://symfony.com/doc/current/rate_limiter.html
 *
 * @author Raziel Rodrigues <raziel.rodrigues@outlook.pt>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final class RateLimit
{
    /**
     * @param string $limiter The configured limiter name
     * @param string[] $methods Request methods to apply the rate limit (`[]` for all)
     */
    public function __construct(
        public string $limiter,
        public array $methods = []
    ) {}
}

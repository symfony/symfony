<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Applies rate limiting to a controller, or method for specific HTTP methods.
 *
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class RequestRateLimit
{
    /** @var string[] */
    public readonly array $rateLimitersFactoriesIds;
    /** @var string[] */
    public readonly array $methods;

    /**
     * @param string[]|string $rateLimitersFactoriesIds The name or names of the configured rate limiter factories
     * @param string[]|string $methods                  HTTP methods to apply rate limiting to. An empty array means all methods are affected
     */
    public function __construct(
        array|string $rateLimitersFactoriesIds,
        array|string $methods = [],
    ) {
        if (!class_exists(RateLimiterFactory::class)) {
            throw new \LogicException('The "RequestRateLimit" attribute requires symfony/rate-limiter. Try running "composer require symfony/rate-limiter".');
        }

        $this->rateLimitersFactoriesIds = (array) $rateLimitersFactoriesIds;
        if ([] === $this->rateLimitersFactoriesIds) {
            throw new \InvalidArgumentException('"rateLimitersFactoriesIds" argument can not be an empty array.');
        }
        $this->methods = (array) $methods;
    }
}

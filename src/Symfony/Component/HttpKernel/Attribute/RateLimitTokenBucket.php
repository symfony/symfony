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

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Rate limits the controller with a token bucket limiter configured inline, without
 * declaring it in "rate_limiter" configuration.
 *
 * @see https://symfony.com/doc/current/rate_limiter.html
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class RateLimitTokenBucket extends AbstractInlineRateLimit
{
    /**
     * @param int                  $limit    Maximum allowed hits in a burst
     * @param \DateInterval|string $interval Interval at which "$amount" tokens are added back
     * @param int                  $amount   Number of tokens added back per "$interval"
     */
    public function __construct(
        public readonly int $limit,
        public readonly \DateInterval|string $interval,
        public readonly int $amount = 1,
        string|Expression|\Closure|null $key = null,
        int $tokens = 1,
        array|string $methods = [],
        bool $exposeHeaders = false,
        bool|Expression|\Closure $if = true,
        ?string $id = null,
    ) {
        parent::__construct($key, $tokens, $methods, $exposeHeaders, $if, $id);
    }

    public function createFactory(RateLimiterBuilder $builder, string $id): RateLimiterFactoryInterface
    {
        return $builder->tokenBucket($id, $this->limit, $this->interval, $this->amount);
    }
}

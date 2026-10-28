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

use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class TokenBucketPolicy implements PolicyInterface
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
    ) {
    }

    public function createFactory(RateLimiterBuilder $builder, string $id): RateLimiterFactoryInterface
    {
        return $builder->tokenBucket($id, $this->limit, $this->interval, $this->amount);
    }
}

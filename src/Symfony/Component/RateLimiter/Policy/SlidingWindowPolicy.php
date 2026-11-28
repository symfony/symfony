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
final class SlidingWindowPolicy implements PolicyInterface
{
    /**
     * @param int                  $limit    Maximum allowed hits for the passed interval
     * @param \DateInterval|string $interval If string, must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent)
     */
    public function __construct(
        public readonly int $limit,
        public readonly \DateInterval|string $interval,
    ) {
    }

    public function createFactory(RateLimiterBuilder $builder, string $id): RateLimiterFactoryInterface
    {
        return $builder->slidingWindow($id, $this->limit, $this->interval);
    }
}

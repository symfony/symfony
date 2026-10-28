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
final class FixedWindowPolicy implements PolicyInterface
{
    /**
     * @param int                            $limit    Maximum allowed hits for the passed interval
     * @param \DateInterval|string           $interval If string, must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent)
     * @param \DateTimeInterface|string|null $anchorAt Align the window to a calendar starting at this datetime instead of the first hit; requires an "$interval" of at least one month
     */
    public function __construct(
        public readonly int $limit,
        public readonly \DateInterval|string $interval,
        public readonly \DateTimeInterface|string|null $anchorAt = null,
    ) {
    }

    public function createFactory(RateLimiterBuilder $builder, string $id): RateLimiterFactoryInterface
    {
        return $builder->fixedWindow($id, $this->limit, $this->interval, $this->anchorAt);
    }
}

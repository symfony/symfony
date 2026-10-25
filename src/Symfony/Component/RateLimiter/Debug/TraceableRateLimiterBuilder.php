<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Debug;

use Symfony\Component\RateLimiter\DataCollector\RateLimiterDataCollector;
use Symfony\Component\RateLimiter\RateLimiterBuilderInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/** @internal */
final class TraceableRateLimiterBuilder implements RateLimiterBuilderInterface
{
    public function __construct(
        private readonly RateLimiterBuilderInterface $inner,
        private readonly RateLimiterDataCollector $collector,
    ) {
    }

    public function slidingWindow(string $id, int $limit, \DateInterval|string $interval): RateLimiterFactoryInterface
    {
        return $this->wrap($this->inner->slidingWindow($id, $limit, $interval), $id, 'sliding_window');
    }

    public function fixedWindow(string $id, int $limit, \DateInterval|string $interval, \DateTimeInterface|string|null $anchorAt = null): RateLimiterFactoryInterface
    {
        return $this->wrap($this->inner->fixedWindow($id, $limit, $interval, $anchorAt), $id, 'fixed_window');
    }

    public function tokenBucket(string $id, int $limit, \DateInterval|string $interval, int $amount = 1): RateLimiterFactoryInterface
    {
        return $this->wrap($this->inner->tokenBucket($id, $limit, $interval, $amount), $id, 'token_bucket');
    }

    public function compound(RateLimiterFactoryInterface ...$factories): RateLimiterFactoryInterface
    {
        $names = array_map(static fn (RateLimiterFactoryInterface $factory) => $factory instanceof TraceableRateLimiterFactory ? $factory->getName() : '?', $factories);

        return $this->wrap($this->inner->compound(...$factories), 'compound('.implode(', ', $names).')', 'compound');
    }

    public function noop(): RateLimiterFactoryInterface
    {
        return $this->wrap($this->inner->noop(), 'noop', 'no_limit');
    }

    private function wrap(RateLimiterFactoryInterface $factory, string $name, string $policy): RateLimiterFactoryInterface
    {
        return new TraceableRateLimiterFactory($factory, $name, $this->collector, $policy);
    }
}

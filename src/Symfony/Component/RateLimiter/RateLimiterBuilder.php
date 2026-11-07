<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Creates rate limiter factories on the fly, for limits that are not configured up front.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RateLimiterBuilder implements RateLimiterBuilderInterface
{
    public function __construct(
        private StorageInterface $storage,
        private ?LockFactory $lockFactory = null,
    ) {
    }

    public function slidingWindow(string $id, int $limit, \DateInterval|string $interval): RateLimiterFactoryInterface
    {
        return $this->factory([
            'id' => $id,
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => $interval,
        ]);
    }

    public function fixedWindow(string $id, int $limit, \DateInterval|string $interval, \DateTimeInterface|string|null $anchorAt = null): RateLimiterFactoryInterface
    {
        return $this->factory([
            'id' => $id,
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => $interval,
            'anchor_at' => $anchorAt,
        ]);
    }

    public function tokenBucket(string $id, int $limit, \DateInterval|string $interval, int $amount = 1): RateLimiterFactoryInterface
    {
        return $this->factory([
            'id' => $id,
            'policy' => 'token_bucket',
            'limit' => $limit,
            'rate' => [
                'interval' => $interval,
                'amount' => $amount,
            ],
        ]);
    }

    public function compound(RateLimiterFactoryInterface ...$factories): RateLimiterFactoryInterface
    {
        return new CompoundRateLimiterFactory($factories);
    }

    public function noop(): RateLimiterFactoryInterface
    {
        return $this->factory([
            'id' => 'noop',
            'policy' => 'no_limit',
        ]);
    }

    private function factory(array $config): RateLimiterFactory
    {
        return new RateLimiterFactory($config, $this->storage, $this->lockFactory);
    }
}

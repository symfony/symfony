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
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/** @internal */
final class TraceableRateLimiterFactory implements RateLimiterFactoryInterface
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $inner,
        private readonly string $name,
        private readonly RateLimiterDataCollector $collector,
        private readonly ?string $policy = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function create(?string $key = null): LimiterInterface
    {
        $limiter = $this->inner->create($key);

        return $limiter instanceof TraceableLimiter ? $limiter : new TraceableLimiter($limiter, $this->name, $this->collector, $key, $this->policy);
    }
}

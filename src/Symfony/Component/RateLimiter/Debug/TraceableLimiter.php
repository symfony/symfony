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
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;

/** @internal */
final class TraceableLimiter implements LimiterInterface
{
    public function __construct(
        private readonly LimiterInterface $inner,
        private readonly string $name,
        private readonly RateLimiterDataCollector $collector,
        private readonly ?string $key = null,
        private readonly ?string $policy = null,
    ) {
    }

    public function consume(int $tokens = 1): RateLimit
    {
        return $this->collector->record($this->name, 'consume', $tokens, fn () => $this->inner->consume($tokens), $this->key, $this->policy);
    }

    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        return $this->collector->record($this->name, 'reserve', $tokens, fn () => $this->inner->reserve($tokens, $maxTime), $this->key, $this->policy);
    }

    public function reset(): void
    {
        $this->collector->record($this->name, 'reset', 0, fn () => $this->inner->reset(), $this->key, $this->policy);
    }
}

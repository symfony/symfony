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

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class CompoundRateLimiterFactory implements RateLimiterFactoryInterface
{
    /**
     * @param iterable<RateLimiterFactoryInterface> $rateLimiterFactories
     */
    public function __construct(private iterable $rateLimiterFactories)
    {
    }

    public function create(?string $key = null): LimiterInterface
    {
        $rateLimiters = [];

        foreach ($this->rateLimiterFactories as $rateLimiterFactory) {
            $rateLimiters[] = $rateLimiterFactory->create($key);
        }

        return new CompoundLimiter($rateLimiters);
    }
}

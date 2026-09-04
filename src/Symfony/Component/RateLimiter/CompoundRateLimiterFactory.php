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
     * @param iterable<array-key, RateLimiterFactoryInterface> $rateLimiterFactories
     * @param array<array-key, string>                         $keys                 Keys to use for the corresponding factories,
     *                                                                               instead of the key passed to create()
     */
    public function __construct(
        private iterable $rateLimiterFactories,
        private array $keys = [],
    ) {
    }

    public function create(?string $key = null): LimiterInterface
    {
        $rateLimiters = [];
        $unknownNames = $this->keys;

        foreach ($this->rateLimiterFactories as $name => $rateLimiterFactory) {
            $rateLimiters[] = $rateLimiterFactory->create($this->keys[$name] ?? $key);
            unset($unknownNames[$name]);
        }

        if ($unknownNames) {
            throw new \LogicException(\sprintf('Unknown rate limiter(s) "%s" in the "$keys" argument of "%s".', implode('", "', array_keys($unknownNames)), self::class));
        }

        return new CompoundLimiter($rateLimiters);
    }
}

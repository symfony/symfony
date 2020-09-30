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
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
final class CompoundLimiter implements LimiterInterface
{
    private $limiters;

    /**
     * @param LimiterInterface[] $limiters
     */
    public function __construct(array $limiters)
    {
        if (!$limiters) {
            throw new \LogicException(sprintf('"%s::%s()" require at least one limiter.', self::class, __METHOD__));
        }
        $this->limiters = $limiters;
    }

    public function consume(int $tokens = 1): Limit
    {
        $minimalLimit = null;
        foreach ($this->limiters as $limiter) {
            $limit = $limiter->consume($tokens);

            if (null === $minimalLimit || $limit->getRemainingTokens() < $minimalLimit->getRemainingTokens()) {
                $minimalLimit = $limit;
            }
        }

        return $minimalLimit;
    }

    public function reset(): void
    {
        foreach ($this->limiters as $limiter) {
            $limiter->reset();
        }
    }
}

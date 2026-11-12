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

use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class CompoundLimiter implements LimiterInterface
{
    /**
     * @param LimiterInterface[] $limiters
     */
    public function __construct(
        private array $limiters,
    ) {
        if (!$limiters) {
            throw new \LogicException(\sprintf('"%s::%s()" require at least one limiter.', self::class, __METHOD__));
        }
    }

    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        throw new ReserveNotSupportedException(__CLASS__);
    }

    public function consume(int $tokens = 1): RateLimit
    {
        $minimalRateLimit = null;
        $resetAt = null;
        foreach ($this->limiters as $limiter) {
            $rateLimit = $limiter->consume($tokens);

            // the latest reset time is when all the consulted limiters are back to their full capacity
            $resetAt = max($resetAt, $rateLimit->getResetAt());

            if (!$rateLimit->isAccepted()) {
                $minimalRateLimit = $rateLimit;
                break;
            }

            if (null === $minimalRateLimit || self::isMoreRestrictive($rateLimit, $minimalRateLimit)) {
                $minimalRateLimit = $rateLimit;
            }
        }

        if ($resetAt == $minimalRateLimit->getResetAt()) {
            return $minimalRateLimit;
        }

        return $minimalRateLimit->withResetAt($resetAt);
    }

    public function reset(): void
    {
        foreach ($this->limiters as $limiter) {
            $limiter->reset();
        }
    }

    private static function isMoreRestrictive(RateLimit $rateLimit, RateLimit $minimalRateLimit): bool
    {
        if ($rateLimit->isAccepted() !== $minimalRateLimit->isAccepted()) {
            return !$rateLimit->isAccepted();
        }

        // the latest retry-after is when all the refusing limiters accept the hit again
        if (!$rateLimit->isAccepted() && $rateLimit->getRetryAfter() != $minimalRateLimit->getRetryAfter()) {
            return $rateLimit->getRetryAfter() > $minimalRateLimit->getRetryAfter();
        }

        if ($rateLimit->getRemainingTokens() !== $minimalRateLimit->getRemainingTokens()) {
            return $rateLimit->getRemainingTokens() < $minimalRateLimit->getRemainingTokens();
        }

        return $rateLimit->getRetryAfter() > $minimalRateLimit->getRetryAfter();
    }
}

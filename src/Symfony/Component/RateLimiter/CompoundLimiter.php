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
        $accepted = [];

        foreach ($this->limiters as $index => $limiter) {
            $rateLimit = $limiter->consume($tokens);

            if (!$rateLimit->isAccepted()) {
                // consuming is all-or-nothing: give back the tokens of the limiters that accepted
                foreach ($accepted as $acceptedIndex => $acceptedRateLimit) {
                    $acceptedLimiter = $this->limiters[$acceptedIndex];

                    if (method_exists($acceptedLimiter, 'refund')) {
                        $acceptedLimiter->refund($acceptedRateLimit);
                    }
                }

                return $rateLimit;
            }

            $accepted[$index] = $rateLimit;

            if (null === $minimalRateLimit || $rateLimit->getRemainingTokens() < $minimalRateLimit->getRemainingTokens()) {
                $minimalRateLimit = $rateLimit;
            }
        }

        return new CompoundRateLimit($accepted, $minimalRateLimit);
    }

    /**
     * Gives back the tokens a consume() call took, when that call should not count.
     *
     * Each sub-limiter receives the rate limit of its own consume() call and ignores
     * the refund when it gave the tokens back on its own already.
     */
    public function refund(RateLimit $rateLimit): RateLimit
    {
        $minimalRateLimit = null;
        $rateLimits = $rateLimit instanceof CompoundRateLimit ? $rateLimit->getRateLimits() : null;
        $refunded = false;

        foreach ($this->limiters as $index => $limiter) {
            if (null !== $rateLimits && !isset($rateLimits[$index])) {
                continue;
            }

            $subRateLimit = $rateLimits[$index] ?? $rateLimit;

            if (method_exists($limiter, 'refund')) {
                $subRateLimit = $limiter->refund($subRateLimit);
            }

            if ($subRateLimit->isRefunded()) {
                $refunded = true;
            }

            if (null === $minimalRateLimit || $subRateLimit->getRemainingTokens() < $minimalRateLimit->getRemainingTokens()) {
                $minimalRateLimit = $subRateLimit;
            }
        }

        if ($refunded) {
            $rateLimit->markRefunded();
        }

        return $minimalRateLimit ?? $rateLimit;
    }

    public function reset(): void
    {
        foreach ($this->limiters as $limiter) {
            $limiter->reset();
        }
    }
}

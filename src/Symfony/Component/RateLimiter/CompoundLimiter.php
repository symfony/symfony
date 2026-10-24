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
                    $this->limiters[$acceptedIndex]->refund($acceptedRateLimit);
                }

                return $rateLimit;
            }

            $accepted[$index] = $rateLimit;

            if (null === $minimalRateLimit || $rateLimit->getRemainingTokens() < $minimalRateLimit->getRemainingTokens()) {
                $minimalRateLimit = $rateLimit;
            }
        }

        return $minimalRateLimit;
    }

    /**
     * Gives back one token of a consume() call that should not count.
     *
     * Each sub-limiter ignores the refund when it gave the token back on its own already.
     */
    public function refund(RateLimit $rateLimit): RateLimit
    {
        $minimalRateLimit = null;

        foreach ($this->limiters as $limiter) {
            $refundedRateLimit = $limiter->refund($rateLimit);

            if (null === $minimalRateLimit || $refundedRateLimit->getRemainingTokens() < $minimalRateLimit->getRemainingTokens()) {
                $minimalRateLimit = $refundedRateLimit;
            }
        }

        return $minimalRateLimit;
    }

    public function reset(): void
    {
        foreach ($this->limiters as $limiter) {
            $limiter->reset();
        }
    }
}

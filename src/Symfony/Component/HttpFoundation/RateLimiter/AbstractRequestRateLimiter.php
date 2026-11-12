<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\RateLimiter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * An implementation of PeekableRequestRateLimiterInterface that
 * fits most use-cases.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
abstract class AbstractRequestRateLimiter implements PeekableRequestRateLimiterInterface
{
    public function consume(Request $request): RateLimit
    {
        return $this->doConsume($request, 1);
    }

    public function peek(Request $request): RateLimit
    {
        return $this->doConsume($request, 0);
    }

    private function doConsume(Request $request, int $tokens): RateLimit
    {
        $limiters = $this->getLimiters($request);
        if (!$limiters) {
            $limiters = [new NoLimiter()];
        }

        $minimalRateLimit = null;
        $resetAt = null;
        foreach ($limiters as $limiter) {
            $rateLimit = $limiter->consume($tokens);

            $minimalRateLimit = $minimalRateLimit ? self::getMinimalRateLimit($minimalRateLimit, $rateLimit) : $rateLimit;

            // the latest reset time is when all the limiters are back to their full capacity
            if (method_exists($rateLimit, 'getResetAt')) {
                $resetAt = max($resetAt, $rateLimit->getResetAt());
            }
        }

        if (null === $resetAt || $resetAt == $minimalRateLimit->getResetAt()) {
            return $minimalRateLimit;
        }

        return new RateLimit($minimalRateLimit->getRemainingTokens(), $minimalRateLimit->getRetryAfter(), $minimalRateLimit->isAccepted(), $minimalRateLimit->getLimit(), $resetAt);
    }

    public function reset(Request $request): void
    {
        foreach ($this->getLimiters($request) as $limiter) {
            $limiter->reset();
        }
    }

    /**
     * @return LimiterInterface[] a set of limiters using keys extracted from the request
     */
    abstract protected function getLimiters(Request $request): array;

    private static function getMinimalRateLimit(RateLimit $first, RateLimit $second): RateLimit
    {
        if ($first->isAccepted() !== $second->isAccepted()) {
            return $first->isAccepted() ? $second : $first;
        }

        $firstRemainingTokens = $first->getRemainingTokens();
        $secondRemainingTokens = $second->getRemainingTokens();

        if ($firstRemainingTokens === $secondRemainingTokens) {
            return $first->getRetryAfter() < $second->getRetryAfter() ? $second : $first;
        }

        return $firstRemainingTokens > $secondRemainingTokens ? $second : $first;
    }
}

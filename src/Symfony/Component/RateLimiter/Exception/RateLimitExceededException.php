<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Exception;

use Symfony\Component\RateLimiter\RateLimit;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class RateLimitExceededException extends \RuntimeException
{
    public function __construct(
        private RateLimit $rateLimit,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('Rate Limit Exceeded', $code, $previous);
    }

    public function getRateLimit(): RateLimit
    {
        return $this->rateLimit;
    }

    public function getRetryAfter(): \DateTimeImmutable
    {
        return $this->rateLimit->getRetryAfter();
    }

    public function getRemainingTokens(): int
    {
        return $this->rateLimit->getRemainingTokens();
    }

    public function getLimit(): int
    {
        return $this->rateLimit->getLimit();
    }
}

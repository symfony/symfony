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

use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

/**
 * @author Valentin Silvestre <vsilvestre.pro@gmail.com>
 */
class RateLimit
{
    public function __construct(
        private int $availableTokens,
        private \DateTimeImmutable $retryAfter,
        private bool $accepted,
        private int $limit,
        private ?\DateTimeImmutable $resetAt = null,
        private int $consumedTokens = 0,
        private ?float $hitTime = null,
        private ?string $limiterId = null,
        private bool $refunded = false,
    ) {
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    /**
     * @return $this
     *
     * @throws RateLimitExceededException if not accepted
     */
    public function ensureAccepted(): static
    {
        if (!$this->accepted) {
            throw new RateLimitExceededException($this);
        }

        return $this;
    }

    public function getRetryAfter(): \DateTimeImmutable
    {
        return $this->retryAfter;
    }

    public function getRemainingTokens(): int
    {
        return $this->availableTokens;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Returns when the limiter is back to its full capacity, or null if the policy has no such moment.
     */
    public function getResetAt(): ?\DateTimeImmutable
    {
        return $this->resetAt;
    }

    /**
     * Returns the number of tokens this rate limit took from its limiter when it was accepted.
     *
     * A rejected rate limit, or one created without consuming any token, returns zero.
     */
    public function getConsumedTokens(): int
    {
        return $this->consumedTokens;
    }

    /**
     * Returns the time at which the tokens were taken, as a Unix timestamp with microseconds, or null
     * when the rate limit was not created by a limiter (which makes it unrefundable).
     */
    public function getHitTime(): ?float
    {
        return $this->hitTime;
    }

    /**
     * @internal
     */
    public function getLimiterId(): ?string
    {
        return $this->limiterId;
    }

    /**
     * Returns whether this rate limit has already been refunded.
     *
     * @internal
     */
    public function isRefunded(): bool
    {
        return $this->refunded;
    }

    /**
     * Marks this rate limit as already refunded, so a second refund has no effect.
     *
     * @internal
     */
    public function markRefunded(): void
    {
        $this->refunded = true;
    }

    public function wait(): void
    {
        $delta = (\PHP_INT_SIZE >= 8 ? $this->retryAfter->getTimestamp() + $this->retryAfter->getMicrosecond() / 1e6 : (float) $this->retryAfter->format('U.u')) - microtime(true);
        if ($delta <= 0) {
            return;
        }

        usleep((int) ($delta * 1e6));
    }
}

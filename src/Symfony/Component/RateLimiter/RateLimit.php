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

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
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
        private ?ClockInterface $clock = null,
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
     * @internal
     */
    public function withResetAt(?\DateTimeImmutable $resetAt): static
    {
        $rateLimit = clone $this;
        $rateLimit->resetAt = $resetAt;

        return $rateLimit;
    }

    public function wait(): void
    {
        $delta = (\PHP_INT_SIZE >= 8 ? $this->retryAfter->getTimestamp() + $this->retryAfter->getMicrosecond() / 1e6 : (float) $this->retryAfter->format('U.u')) - ($this->clock ? (float) $this->clock->now()->format('U.u') : microtime(true));
        if ($delta <= 0) {
            return;
        }

        if ($this->clock instanceof SymfonyClockInterface) {
            $this->clock->sleep($delta);

            return;
        }

        // on 32-bit platforms, usleep() cannot take more than about 35 minutes
        if (1 <= $seconds = (int) $delta) {
            sleep($seconds);
            $delta -= $seconds;
        }

        usleep((int) ceil($delta * 1e6));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Policy;

use Symfony\Component\Lock\LockInterface;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class TokenBucketLimiter implements LimiterInterface
{
    use ResetLimiterTrait;

    public function __construct(
        string $id,
        private int $maxBurst,
        private Rate $rate,
        StorageInterface $storage,
        ?LockInterface $lock = null,
    ) {
        $this->id = $id;
        $this->maxBurst = min($maxBurst, TokenBucket::MAX_BURST_SIZE);
        $this->storage = $storage;
        $this->lock = $lock;
    }

    /**
     * Waits until the required number of tokens is available.
     *
     * The reserved tokens will be taken into account when calculating
     * future token consumptions. Do not use this method if you intend
     * to skip this process.
     *
     * @param int        $tokens  the number of tokens required
     * @param float|null $maxTime maximum accepted waiting time in seconds
     *
     * @throws MaxWaitDurationExceededException if $maxTime is set and the process needs to wait longer than its value (in seconds)
     * @throws \InvalidArgumentException        if $tokens is larger than the maximum burst size
     */
    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        if ($tokens > $this->maxBurst) {
            throw new \InvalidArgumentException(\sprintf('Cannot reserve more tokens (%d) than the burst size of the rate limiter (%d).', $tokens, $this->maxBurst));
        }

        $this->lock?->acquire(true);

        try {
            $bucket = $this->storage->fetch($this->id);
            if (!$bucket instanceof TokenBucket) {
                $bucket = new TokenBucket($this->id, $this->maxBurst, $this->rate);
            }

            $bucket->configure($this->maxBurst, $this->rate);

            $now = microtime(true);
            $availableTokens = $bucket->getAvailableTokens($now);

            if ($availableTokens > $this->maxBurst) {
                $availableTokens = $this->maxBurst;
            }

            if ($availableTokens >= $tokens) {
                // tokens are now available, update bucket
                $bucket->setTokens($availableTokens - $tokens);

                $retryAfter = $now;
                if ($availableTokens === $tokens) {
                    $retryAfter += $this->rate->calculateTimeForTokens(1);
                }

                $reservation = new Reservation($now, new RateLimit($bucket->getAvailableTokens($now), \DateTimeImmutable::createFromTimestamp($availableTokens === $tokens ? ceil($retryAfter) : floor($retryAfter)), true, $this->maxBurst, $this->resetAt($availableTokens - $tokens, $now)));
            } else {
                // peeking with 0 tokens tells when the next token is available
                $remainingTokens = max(1, $tokens) - $availableTokens;
                $waitDuration = $this->rate->calculateTimeForTokens($remainingTokens);

                if (null !== $maxTime && $waitDuration > $maxTime) {
                    // process needs to wait longer than set interval
                    $rateLimit = new RateLimit($availableTokens, \DateTimeImmutable::createFromTimestamp(ceil($now + $waitDuration)), false, $this->maxBurst, $this->resetAt($availableTokens, $now));

                    throw new MaxWaitDurationExceededException(\sprintf('The rate limiter wait time ("%d" seconds) is longer than the provided maximum time ("%d" seconds).', $waitDuration, $maxTime), $rateLimit);
                }

                // at $now + $waitDuration all tokens will be reserved for this process,
                // so no tokens are left for other processes.
                $bucket->setTokens($availableTokens - $tokens);

                $reservation = new Reservation($now + $waitDuration, new RateLimit(0, \DateTimeImmutable::createFromTimestamp(ceil($now + $waitDuration)), false, $this->maxBurst, $this->resetAt($availableTokens - $tokens, $now)));
            }

            if (0 !== $tokens) {
                $this->storage->save($bucket);
            }
        } finally {
            $this->lock?->release();
        }

        return $reservation;
    }

    public function consume(int $tokens = 1): RateLimit
    {
        try {
            return $this->reserve($tokens, 0)->getRateLimit();
        } catch (MaxWaitDurationExceededException $e) {
            return $e->getRateLimit();
        }
    }

    /**
     * Gives back one token of a consume() call that should not count.
     *
     * The bucket refills on its own, so refunding a token that has been
     * refilled in the meantime has no effect: the token is back already.
     */
    public function refund(RateLimit $rateLimit): RateLimit
    {
        if (!$rateLimit->isAccepted()) {
            return $rateLimit;
        }

        $this->lock?->acquire(true);

        try {
            $bucket = $this->storage->fetch($this->id);

            if (!$bucket instanceof TokenBucket) {
                return $rateLimit;
            }

            $bucket->configure($this->maxBurst, $this->rate);

            $now = microtime(true);

            // the token has been given back when the bucket is full again, which the reset time of the consumed limit tells
            if (null === $rateLimit->getResetAt() || (int) $now >= $rateLimit->getResetAt()->getTimestamp()) {
                return $rateLimit;
            }

            $availableTokens = min($this->maxBurst, $bucket->getAvailableTokens($now) + 1);
            $bucket->setTokens($availableTokens);
            $this->storage->save($bucket);

            return new RateLimit($availableTokens, \DateTimeImmutable::createFromTimestamp((int) ceil($now)), true, $this->maxBurst, $this->resetAt($availableTokens, $now));
        } finally {
            $this->lock?->release();
        }
    }

    private function resetAt(int $availableTokens, float $now): \DateTimeImmutable
    {
        if ($availableTokens >= $this->maxBurst) {
            return \DateTimeImmutable::createFromTimestamp((int) $now);
        }

        return \DateTimeImmutable::createFromTimestamp((int) ceil($now + $this->rate->calculateTimeForTokens($this->maxBurst - $availableTokens)));
    }
}

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

use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
final class TokenBucketLimiter implements LimiterInterface
{
    private $id;
    private $maxBurst;
    private $rate;
    private $storage;
    private $lock;

    use ResetLimiterTrait;

    public function __construct(string $id, int $maxBurst, Rate $rate, StorageInterface $storage, ?LockInterface $lock = null)
    {
        $this->id = $id;
        $this->maxBurst = $maxBurst;
        $this->rate = $rate;
        $this->storage = $storage;
        $this->lock = $lock ?? new NoLock();
    }

    /**
     * Waits until the required number of tokens is available.
     *
     * The reserved tokens will be taken into account when calculating
     * future token consumptions. Do not use this method if you intend
     * to skip this process.
     *
     * @param int   $tokens  the number of tokens required
     * @param float $maxTime maximum accepted waiting time in seconds
     *
     * @throws MaxWaitDurationExceededException if $maxTime is set and the process needs to wait longer than its value (in seconds)
     * @throws \InvalidArgumentException        if $tokens is larger than the maximum burst size
     */
    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        if ($tokens > $this->maxBurst) {
            throw new \InvalidArgumentException(sprintf('Cannot reserve more tokens (%d) than the burst size of the rate limiter (%d).', $tokens, $this->maxBurst));
        }

        $this->lock->acquire(true);

        try {
            $bucket = $this->storage->fetch($this->id);
            if (null === $bucket) {
                $bucket = new TokenBucket($this->id, $this->maxBurst, $this->rate);
            }

            $now = microtime(true);
            $availableTokens = $bucket->getAvailableTokens($now);
            if ($availableTokens >= $tokens) {
                // tokens are now available, update bucket
                $bucket->setTokens($availableTokens - $tokens);
                $bucket->setTimer($now);

                $reservation = new Reservation($now);
            } else {
                $remainingTokens = $tokens - $availableTokens;
                $waitDuration = $this->rate->calculateTimeForTokens($remainingTokens);

                if (null !== $maxTime && $waitDuration > $maxTime) {
                    // process needs to wait longer than set interval
                    throw new MaxWaitDurationExceededException(sprintf('The rate limiter wait time ("%d" seconds) is longer than the provided maximum time ("%d" seconds).', $waitDuration, $maxTime));
                }

                // at $now + $waitDuration all tokens will be reserved for this process,
                // so no tokens are left for other processes.
                $bucket->setTokens(0);
                $bucket->setTimer($now + $waitDuration);

                $reservation = new Reservation($bucket->getTimer());
            }

            $this->storage->save($bucket);
        } finally {
            $this->lock->release();
        }

        return $reservation;
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $tokens = 1): bool
    {
        try {
            $this->reserve($tokens, 0);

            return true;
        } catch (MaxWaitDurationExceededException $e) {
            return false;
        }
    }
}

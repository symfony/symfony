<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Storage;

use Symfony\Component\RateLimiter\LimiterStateInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.3
 */
class InMemoryStorage implements StorageInterface
{
    private $buckets = [];

    public function save(LimiterStateInterface $limiterState): void
    {
        if (isset($this->buckets[$limiterState->getId()])) {
            [$expireAt, ] = $this->buckets[$limiterState->getId()];
        }

        if (null !== ($expireSeconds = $limiterState->getExpirationTime())) {
            $expireAt = microtime(true) + $expireSeconds;
        }

        $this->buckets[$limiterState->getId()] = [$expireAt, serialize($limiterState)];
    }

    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        if (!isset($this->buckets[$limiterStateId])) {
            return null;
        }

        [$expireAt, $limiterState] = $this->buckets[$limiterStateId];
        if (null !== $expireAt && $expireAt <= microtime(true)) {
            unset($this->buckets[$limiterStateId]);

            return null;
        }

        return unserialize($limiterState);
    }

    public function delete(string $limiterStateId): void
    {
        if (!isset($this->buckets[$limiterStateId])) {
            return;
        }

        unset($this->buckets[$limiterStateId]);
    }
}

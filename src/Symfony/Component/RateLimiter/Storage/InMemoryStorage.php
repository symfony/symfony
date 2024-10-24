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

use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\ClockTrait;
use Symfony\Component\RateLimiter\LimiterStateInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class InMemoryStorage implements StorageInterface
{
    use ClockTrait;

    private array $buckets = [];

    public function __construct(?ClockInterface $clock = null)
    {
        $this->setClock($clock);
    }

    public function save(LimiterStateInterface $limiterState): void
    {
        $this->buckets[$limiterState->getId()] = [$this->getExpireAt($limiterState), serialize($limiterState)];
    }

    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        if (!isset($this->buckets[$limiterStateId])) {
            return null;
        }

        [$expireAt, $limiterState] = $this->buckets[$limiterStateId];
        if (null !== $expireAt && $expireAt <= $this->now()) {
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

    private function getExpireAt(LimiterStateInterface $limiterState): ?float
    {
        if (null !== $expireSeconds = $limiterState->getExpirationTime()) {
            return $this->now() + $expireSeconds;
        }

        return $this->buckets[$limiterState->getId()][0] ?? null;
    }
}

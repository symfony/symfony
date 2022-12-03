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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\RateLimiter\LimiterStateInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class CacheStorage implements StorageInterface
{
    private CacheItemPoolInterface $pool;

    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
    }

    public function save(LimiterStateInterface $limiterState): void
    {
        $cacheItem = $this->pool->getItem(sha1($limiterState->getId()));
        $cacheItem->set($limiterState);
        if (null !== ($expireAfter = $limiterState->getExpirationTime())) {
            $cacheItem->expiresAfter($expireAfter);
        }

        $this->pool->save($cacheItem);
    }

    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        $cacheItem = $this->pool->getItem(sha1($limiterStateId));
        $value = $cacheItem->get();
        if ($value instanceof LimiterStateInterface) {
            return $value;
        }

        return null;
    }

    public function delete(string $limiterStateId): void
    {
        $this->pool->deleteItem(sha1($limiterStateId));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Loads cache misses with a single callback.
 *
 * An optional lock key uses the pool's stampede protection for cache misses.
 * The pool's locking limits apply, and batches need the same lock key to coordinate.
 * Early expiration is not supported.
 * Saving misses commits every pending item in the pool.
 */
final class BatchCache
{
    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {
    }

    /**
     * Returns values in the order of the requested keys.
     *
     * The callback receives missing items and must return values for exactly those keys.
     * Numeric string keys are converted according to PHP's array rules.
     * Providing a lock key requires a pool that implements CacheInterface.
     * The lock key only coordinates computations.
     *
     * @param string[]                                                                  $keys
     * @param callable(array<string|int, CacheItemInterface>): array<string|int, mixed> $callback
     *
     * @param-immediately-invoked-callable $callback
     *
     * @return array<string|int, mixed>
     */
    public function get(array $keys, callable $callback, ?string $lockKey = null): array
    {
        if (null !== $lockKey) {
            CacheItem::validateKey($lockKey);
            if (!$this->pool instanceof CacheInterface) {
                throw new InvalidArgumentException(\sprintf('The pool must implement "%s" when a lock key is provided.', CacheInterface::class));
            }
        }

        $requested = [];
        foreach ($keys as $key) {
            CacheItem::validateKey($key);
            $requested[$key] = $key;
        }

        if (!$requested) {
            return [];
        }

        $items = [];
        foreach ($this->pool->getItems(array_values($requested)) as $item) {
            $items[$item->getKey()] = $item;
        }

        $values = $missing = [];
        foreach ($requested as $key) {
            $item = $items[$key];
            if ($item->isHit()) {
                $values[$key] = $item->get();
            } else {
                $values[$key] = null;
                $missing[$key] = $item;
            }
        }

        if (!$missing) {
            return $values;
        }

        if (null !== $lockKey) {
            return $this->pool->get($lockKey, function (CacheItemInterface $item, bool &$save) use ($keys, $callback): array {
                $save = false;

                return $this->get($keys, $callback);
            }, \INF);
        }

        $callbackItems = $missing;
        $computed = $callback($callbackItems);

        if (!\is_array($computed)) {
            throw new InvalidArgumentException(\sprintf('The callback must return an array of values keyed by the missing cache keys, "%s" returned.', get_debug_type($computed)));
        }
        if (array_diff_key($missing, $computed) || array_diff_key($computed, $missing)) {
            throw new InvalidArgumentException('The callback must return a value for each missing cache key and no other keys.');
        }

        foreach ($missing as $key => $item) {
            $values[$key] = $computed[$key];
            $this->pool->saveDeferred($item->set($computed[$key]));
        }
        $this->pool->commit();

        return $values;
    }
}

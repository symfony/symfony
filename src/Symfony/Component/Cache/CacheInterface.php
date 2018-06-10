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

/**
 * Gets and stores items from a cache.
 *
 * On cache misses, a callback is called that should return the missing value.
 * It is given two arguments:
 * - the missing cache key
 * - the corresponding PSR-6 CacheItemInterface object,
 *   allowing time-based expiration control.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface CacheInterface
{
    /**
     * @param callable(CacheItem):mixed $callback Should return the computed value for the given key/item
     *
     * @return mixed The value corresponding to the provided key
     */
    public function get(string $key, callable $callback);
}

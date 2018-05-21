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
 * Gets and stores items from a tag-aware cache.
 *
 * On cache misses, a callback is called that should return the missing value.
 * It is given two arguments:
 * - the missing cache key
 * - the corresponding Symfony CacheItem object,
 *   allowing time-based *and* tags-based expiration control
 *
 * If you don't need tags-based invalidation, use CacheInterface instead.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface TaggableCacheInterface extends CacheInterface
{
    /**
     * @param callable(CacheItem):mixed $callback Should return the computed value for the given key/item
     *
     * @return mixed The value corresponding to the provided key
     */
    public function get(string $key, callable $callback);
}

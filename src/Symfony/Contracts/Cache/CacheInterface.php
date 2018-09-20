<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Cache;

use Psr\Cache\InvalidArgumentException;

/**
 * Gets/Stores items from/to a cache.
 *
 * On cache misses, a callback is called that should return the missing value.
 * This callback is given an ItemInterface object corresponding to the needed key,
 * that could be used e.g. for expiration control.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface CacheInterface
{
    /**
     * @param string                        $key      The key of the item to retrieve from the cache
     * @param callable(ItemInterface):mixed $callback Should return the computed value for the given key/item
     * @param float|null                    $beta     A float that, as it grows, controls the likeliness of triggering
     *                                                early expiration. 0 disables it, INF forces immediate expiration.
     *                                                The default (or providing null) is implementation dependent but should
     *                                                typically be 1.0, which should provide optimal stampede protection.
     *                                                See https://en.wikipedia.org/wiki/Cache_stampede#Probabilistic_early_expiration
     *
     * @return mixed The value corresponding to the provided key
     *
     * @throws InvalidArgumentException When $key is not valid or when $beta is negative
     */
    public function get(string $key, callable $callback, float $beta = null);
}

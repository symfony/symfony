<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Driver;

/**
 * Interface for cache drivers
 *
 * This is adapted from the PSR proposal and the Symfony2 talks about the cache component here
 * @link https://github.com/evert/fig-standards/blob/master/proposed/objectcache.md
 * @link https://github.com/symfony/symfony/pull/3211
 *
 * @author  Florin Patan <florinpatan@gmail.com>
 */
interface DriverInterface
{
    /**
     * Set data into cache.
     *
     * @param string $key      Entry id
     * @param mixed  $value    Cache entry
     * @param int    $lifeTime Life time of the cache entry
     *
     * @return boolean
     */
    public function set($key, $value, $lifeTime = 0);

    /**
     * Check if an entry exists in cache
     *
     * @param string $key Entry id
     *
     * @return boolean
     */
    public function exists($key);

    /**
     * Get an entry from the cache
     *
     * @param string $key Entry id
     * @param boolean|null $exists If the operation was succesfull or not
     *
     * @return mixed The cached data or FALSE
     */
    public function get($key, &$exists = null);

    /**
     * Removes a cache entry
     *
     * @param string $key Entry id
     *
     * @return boolean
     */
    public function remove($key);

    /**
     * If this driver has support for serialization or not
     *
     * @return boolean
     */
    public function hasSerializationSupport();

}

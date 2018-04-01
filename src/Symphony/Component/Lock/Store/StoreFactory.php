<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Lock\Store;

use Symphony\Component\Cache\Traits\RedisProxy;
use Symphony\Component\Lock\Exception\InvalidArgumentException;

/**
 * StoreFactory create stores and connections.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class StoreFactory
{
    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client|\Memcached $connection
     *
     * @return RedisStore|MemcachedStore
     */
    public static function createStore($connection)
    {
        if ($connection instanceof \Redis || $connection instanceof \RedisArray || $connection instanceof \RedisCluster || $connection instanceof \Predis\Client || $connection instanceof RedisProxy) {
            return new RedisStore($connection);
        }
        if ($connection instanceof \Memcached) {
            return new MemcachedStore($connection);
        }

        throw new InvalidArgumentException(sprintf('Unsupported Connection: %s.', get_class($connection)));
    }
}

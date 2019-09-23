<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\Lock\Exception\InvalidArgumentException;

/**
 * StoreFactory create stores and connections.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class StoreFactory
{
    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Memcached $connection
     *
     * @return RedisStore|MemcachedStore
     */
    public static function createStore($connection)
    {
        if ($connection instanceof \Redis || $connection instanceof \RedisArray || $connection instanceof \RedisCluster || $connection instanceof \Predis\ClientInterface || $connection instanceof RedisProxy) {
            return new RedisStore($connection);
        }
        if ($connection instanceof \Memcached) {
            return new MemcachedStore($connection);
        }

        throw new InvalidArgumentException(sprintf('Unsupported Connection: %s.', \get_class($connection)));
    }
}

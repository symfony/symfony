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

use Symfony\Component\Lock\Exception\InvalidArgumentException;

/**
 * StoreFactory create stores and connections.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class StoreFactory
{
    public static function createConnection($dsn, array $options = array())
    {
        if (!is_string($dsn)) {
            throw new InvalidArgumentException(sprintf('The %s() method expects argument #1 to be string, %s given.', __METHOD__, gettype($dsn)));
        }
        if (0 === strpos($dsn, 'redis://')) {
            return RedisStore::createConnection($dsn, $options);
        }
        if (0 === strpos($dsn, 'memcached://')) {
            return MemcachedStore::createConnection($dsn, $options);
        }

        throw new InvalidArgumentException(sprintf('Unsupported DSN: %s.', $dsn));
    }

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client|\Memcached $connection
     *
     * @return RedisStore|MemcachedStore
     */
    public static function createStore($connection)
    {
        if ($connection instanceof \Redis || $connection instanceof \RedisArray || $connection instanceof \RedisCluster || $connection instanceof \Predis\Client) {
            return new RedisStore($connection);
        }
        if ($connection instanceof \Memcached) {
            return new MemcachedStore($connection);
        }

        throw new InvalidArgumentException(sprintf('Unsupported Connection: %s.', get_class($connection)));
    }
}

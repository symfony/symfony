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

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\StoreInterface;

/**
 * StoreFactory create stores and connections.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class StoreFactory
{
    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Memcached|\Zookeeper|string $connection Connection or DSN or Store short name
     *
     * @return StoreInterface
     */
    public static function createStore($connection)
    {
        if (
            $connection instanceof \Redis ||
            $connection instanceof \RedisArray ||
            $connection instanceof \RedisCluster ||
            $connection instanceof \Predis\ClientInterface ||
            $connection instanceof RedisProxy ||
            $connection instanceof RedisClusterProxy
        ) {
            return new RedisStore($connection);
        }
        if ($connection instanceof \Memcached) {
            return new MemcachedStore($connection);
        }
        if ($connection instanceof \Zookeeper) {
            return new ZookeeperStore($connection);
        }
        if (!\is_string($connection)) {
            throw new InvalidArgumentException(sprintf('Unsupported Connection: %s.', \get_class($connection)));
        }

        switch (true) {
            case 'flock' === $connection:
                return new FlockStore();
            case 0 === strpos($connection, 'flock://'):
                return new FlockStore(substr($connection, 8));
            case 'semaphore' === $connection:
                return new SemaphoreStore();
            case class_exists(AbstractAdapter::class) && preg_match('#^[a-z]++://#', $connection):
                return static::createStore(AbstractAdapter::createConnection($connection));
            default:
                throw new InvalidArgumentException(sprintf('Unsupported Connection: %s.', $connection));
        }
    }
}

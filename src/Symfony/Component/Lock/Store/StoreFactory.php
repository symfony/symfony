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
use Symfony\Component\Lock\PersistingStoreInterface;

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
     * @return PersistingStoreInterface
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
            case 0 === strpos($connection, 'redis://') && class_exists(AbstractAdapter::class):
            case 0 === strpos($connection, 'rediss://') && class_exists(AbstractAdapter::class):
                return new RedisStore(AbstractAdapter::createConnection($connection, ['lazy' => true]));
            case 0 === strpos($connection, 'memcached://') && class_exists(AbstractAdapter::class):
                return new MemcachedStore(AbstractAdapter::createConnection($connection, ['lazy' => true]));
            case 0 === strpos($connection, 'sqlite:'):
            case 0 === strpos($connection, 'mysql:'):
            case 0 === strpos($connection, 'pgsql:'):
            case 0 === strpos($connection, 'oci:'):
            case 0 === strpos($connection, 'sqlsrv:'):
            case 0 === strpos($connection, 'sqlite3://'):
            case 0 === strpos($connection, 'mysql2://'):
            case 0 === strpos($connection, 'postgres://'):
            case 0 === strpos($connection, 'postgresql://'):
            case 0 === strpos($connection, 'mssql://'):
                return new PdoStore($connection);
            case 0 === strpos($connection, 'zookeeper://'):
                return new ZookeeperStore(ZookeeperStore::createConnection($connection));
            default:
                throw new InvalidArgumentException(sprintf('Unsupported Connection: %s.', $connection));
        }
    }
}

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

use Doctrine\DBAL\Connection;
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
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|RedisProxy|RedisClusterProxy|\Memcached|\PDO|Connection|\Zookeeper|string $connection Connection or DSN or Store short name
     *
     * @return PersistingStoreInterface
     */
    public static function createStore($connection)
    {
        if (!\is_string($connection) && !\is_object($connection)) {
            throw new \TypeError(sprintf('Argument 1 passed to %s() must be a string or a connection object, %s given.', __METHOD__, \gettype($connection)));
        }

        switch (true) {
            case $connection instanceof \Redis:
            case $connection instanceof \RedisArray:
            case $connection instanceof \RedisCluster:
            case $connection instanceof \Predis\ClientInterface:
            case $connection instanceof RedisProxy:
            case $connection instanceof RedisClusterProxy:
                return new RedisStore($connection);

            case $connection instanceof \Memcached:
                return new MemcachedStore($connection);

            case $connection instanceof \PDO:
            case $connection instanceof Connection:
                return new PdoStore($connection);

            case $connection instanceof \Zookeeper:
                return new ZookeeperStore($connection);

            case !\is_string($connection):
                throw new InvalidArgumentException(sprintf('Unsupported Connection: %s.', \get_class($connection)));
            case 'flock' === $connection:
                return new FlockStore();

            case 0 === strpos($connection, 'flock://'):
                return new FlockStore(substr($connection, 8));

            case 'semaphore' === $connection:
                return new SemaphoreStore();

            case 0 === strpos($connection, 'redis://'):
            case 0 === strpos($connection, 'rediss://'):
            case 0 === strpos($connection, 'memcached://'):
                if (!class_exists(AbstractAdapter::class)) {
                    throw new InvalidArgumentException(sprintf('Unsupported DSN "%s". Try running "composer require symfony/cache".', $connection));
                }
                $storeClass = 0 === strpos($connection, 'memcached://') ? MemcachedStore::class : RedisStore::class;
                $connection = AbstractAdapter::createConnection($connection, ['lazy' => true]);

                return new $storeClass($connection);

            case 0 === strpos($connection, 'mssql://'):
            case 0 === strpos($connection, 'mysql:'):
            case 0 === strpos($connection, 'mysql2://'):
            case 0 === strpos($connection, 'oci:'):
            case 0 === strpos($connection, 'oci8://'):
            case 0 === strpos($connection, 'pdo_oci://'):
            case 0 === strpos($connection, 'pgsql:'):
            case 0 === strpos($connection, 'postgres://'):
            case 0 === strpos($connection, 'postgresql://'):
            case 0 === strpos($connection, 'sqlsrv:'):
            case 0 === strpos($connection, 'sqlite:'):
            case 0 === strpos($connection, 'sqlite3://'):
                return new PdoStore($connection);

            case 0 === strpos($connection, 'zookeeper://'):
                return new ZookeeperStore(ZookeeperStore::createConnection($connection));
        }

        throw new InvalidArgumentException(sprintf('Unsupported Connection: %s.', $connection));
    }
}

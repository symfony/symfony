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
            throw new \TypeError(sprintf('Argument 1 passed to "%s()" must be a string or a connection object, "%s" given.', __METHOD__, \gettype($connection)));
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
                throw new InvalidArgumentException(sprintf('Unsupported Connection: "%s".', \get_class($connection)));
            case 'flock' === $connection:
                return new FlockStore();

            case str_starts_with($connection, 'flock://'):
                return new FlockStore(substr($connection, 8));

            case 'semaphore' === $connection:
                return new SemaphoreStore();

            case str_starts_with($connection, 'redis:'):
            case str_starts_with($connection, 'rediss:'):
            case str_starts_with($connection, 'memcached:'):
                if (!class_exists(AbstractAdapter::class)) {
                    throw new InvalidArgumentException(sprintf('Unsupported DSN "%s". Try running "composer require symfony/cache".', $connection));
                }
                $storeClass = str_starts_with($connection, 'memcached:') ? MemcachedStore::class : RedisStore::class;
                $connection = AbstractAdapter::createConnection($connection, ['lazy' => true]);

                return new $storeClass($connection);

            case str_starts_with($connection, 'mssql://'):
            case str_starts_with($connection, 'mysql:'):
            case str_starts_with($connection, 'mysql2://'):
            case str_starts_with($connection, 'oci:'):
            case str_starts_with($connection, 'oci8://'):
            case str_starts_with($connection, 'pdo_oci://'):
            case str_starts_with($connection, 'pgsql:'):
            case str_starts_with($connection, 'postgres://'):
            case str_starts_with($connection, 'postgresql://'):
            case str_starts_with($connection, 'sqlsrv:'):
            case str_starts_with($connection, 'sqlite:'):
            case str_starts_with($connection, 'sqlite3://'):
                return new PdoStore($connection);

            case str_starts_with($connection, 'zookeeper://'):
                return new ZookeeperStore(ZookeeperStore::createConnection($connection));
        }

        throw new InvalidArgumentException(sprintf('Unsupported Connection: "%s".', $connection));
    }
}

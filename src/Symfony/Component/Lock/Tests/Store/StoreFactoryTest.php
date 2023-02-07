<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Store\PdoStore;
use Symfony\Component\Lock\Store\PostgreSqlStore;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Store\StoreFactory;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class StoreFactoryTest extends TestCase
{
    /**
     * @dataProvider validConnections
     */
    public function testCreateStore($connection, string $expectedStoreClass)
    {
        $store = StoreFactory::createStore($connection);

        $this->assertInstanceOf($expectedStoreClass, $store);
    }

    public static function validConnections(): \Generator
    {
        if (class_exists(\Redis::class)) {
            yield [new \Redis(), RedisStore::class];
        }
        yield [new \Predis\Client(), RedisStore::class];
        if (class_exists(\Memcached::class)) {
            yield [new \Memcached(), MemcachedStore::class];
        }
        if (\extension_loaded('sysvsem')) {
            yield ['semaphore', SemaphoreStore::class];
        }
        if (class_exists(AbstractAdapter::class) && MemcachedAdapter::isSupported()) {
            yield ['memcached://server.com', MemcachedStore::class];
            yield ['memcached:?host[localhost]&host[localhost:12345]', MemcachedStore::class];
        }
        if (class_exists(\Redis::class) && class_exists(AbstractAdapter::class)) {
            yield ['redis://localhost', RedisStore::class];
            yield ['redis://localhost?lazy=1', RedisStore::class];
            yield ['redis://localhost?redis_cluster=1', RedisStore::class];
            yield ['redis://localhost?redis_cluster=1&lazy=1', RedisStore::class];
            yield ['redis:?host[localhost]&host[localhost:6379]&redis_cluster=1', RedisStore::class];
        }
        if (class_exists(\PDO::class)) {
            yield ['sqlite:/tmp/sqlite.db', PdoStore::class];
            yield ['sqlite::memory:', PdoStore::class];
            yield ['mysql:host=localhost;dbname=test;', PdoStore::class];
            yield ['pgsql:host=localhost;dbname=test;', PdoStore::class];
            yield ['pgsql+advisory:host=localhost;dbname=test;', PostgreSqlStore::class];
            yield ['oci:host=localhost;dbname=test;', PdoStore::class];
            yield ['sqlsrv:server=localhost;Database=test', PdoStore::class];
        }
        if (class_exists(Connection::class)) {
            yield ['mysql://server.com/test', DoctrineDbalStore::class];
            yield ['mysql2://server.com/test', DoctrineDbalStore::class];
            yield ['pgsql://server.com/test', DoctrineDbalStore::class];
            yield ['postgres://server.com/test', DoctrineDbalStore::class];
            yield ['postgresql://server.com/test', DoctrineDbalStore::class];
            yield ['sqlite:///tmp/test', DoctrineDbalStore::class];
            yield ['sqlite3:///tmp/test', DoctrineDbalStore::class];
            yield ['oci8://server.com/test', DoctrineDbalStore::class];
            yield ['mssql://server.com/test', DoctrineDbalStore::class];
            yield ['pgsql+advisory://server.com/test', DoctrineDbalPostgreSqlStore::class];
            yield ['postgres+advisory://server.com/test', DoctrineDbalPostgreSqlStore::class];
            yield ['postgresql+advisory://server.com/test', DoctrineDbalPostgreSqlStore::class];
        }

        yield ['in-memory', InMemoryStore::class];

        yield ['flock', FlockStore::class];
        yield ['flock://'.sys_get_temp_dir(), FlockStore::class];
    }
}

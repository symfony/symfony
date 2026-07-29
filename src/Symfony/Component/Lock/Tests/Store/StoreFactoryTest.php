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

use AsyncAws\DynamoDb\DynamoDbClient;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Lock\Bridge\DynamoDb\Store\DynamoDbStore;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Store\NullStore;
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
    #[DataProvider('validConnections')]
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
        if (class_exists(DynamoDbClient::class)) {
            yield ['dynamodb://default', DynamoDbStore::class];
        }

        yield ['in-memory', InMemoryStore::class];

        yield ['flock', FlockStore::class];
        yield ['flock://'.sys_get_temp_dir(), FlockStore::class];

        yield ['null', NullStore::class];
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCreateStoreForPdoConnectionWithoutAdvisory()
    {
        $store = StoreFactory::createStore(new \PDO('sqlite::memory:'));

        $this->assertInstanceOf(PdoStore::class, $store);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testCreateAdvisoryStoreForPdoConnectionRoutesToPostgreSqlStore()
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // PostgreSqlStore rejects non-pgsql drivers in its constructor, which proves the
        // factory routed the connection to the advisory store rather than PdoStore.
        $this->expectException(\Symfony\Component\Lock\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage(PostgreSqlStore::class);

        StoreFactory::createStore($pdo, ['advisory' => true]);
    }

    public function testCreateStoreForDbalConnectionWithoutAdvisory()
    {
        if (!class_exists(Connection::class)) {
            $this->markTestSkipped('The "doctrine/dbal" package is required.');
        }

        $store = StoreFactory::createStore($this->createStub(Connection::class));

        $this->assertInstanceOf(DoctrineDbalStore::class, $store);
    }

    public function testCreateAdvisoryStoreForDbalConnectionRoutesToDoctrineDbalPostgreSqlStore()
    {
        if (!class_exists(Connection::class) || !class_exists(PostgreSQLPlatform::class)) {
            $this->markTestSkipped('The "doctrine/dbal" package with the PostgreSQL platform is required.');
        }

        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());

        $store = StoreFactory::createStore($connection, ['advisory' => true]);

        $this->assertInstanceOf(DoctrineDbalPostgreSqlStore::class, $store);
    }
}

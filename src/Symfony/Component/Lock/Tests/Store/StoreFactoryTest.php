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
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Lock\Bridge\DynamoDb\Store\DynamoDbStore;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Store\DoctrineDbalMysqlStore;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Store\MysqlStore;
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

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testCreateStoreForPdoConnectionWithoutAdvisory()
    {
        $store = StoreFactory::createStore(new \PDO('sqlite::memory:'));

        $this->assertInstanceOf(PdoStore::class, $store);
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testCreateAdvisoryStoreRejectsUnsupportedPdoDriver()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "sqlite" PDO driver does not support advisory locks.');

        StoreFactory::createStore(new \PDO('sqlite::memory:'), true);
    }

    public function testCreateAdvisoryStoreForPgsqlPdoConnection()
    {
        // the connection is never used, only its driver name is read to route to the advisory store
        $store = StoreFactory::createStore($this->createPdoStub('pgsql'), true);

        $this->assertInstanceOf(PostgreSqlStore::class, $store);
    }

    public function testCreateAdvisoryStoreForMysqlPdoConnection()
    {
        $store = StoreFactory::createStore($this->createPdoStub('mysql'), true);

        $this->assertInstanceOf(MysqlStore::class, $store);
    }

    public function testCreateStoreForDbalConnectionWithoutAdvisory()
    {
        if (!class_exists(Connection::class)) {
            $this->markTestSkipped('The "doctrine/dbal" package is required.');
        }

        $store = StoreFactory::createStore($this->createStub(Connection::class));

        $this->assertInstanceOf(DoctrineDbalStore::class, $store);
    }

    #[DataProvider('advisoryDbalPlatforms')]
    public function testCreateAdvisoryStoreForDbalConnection(string $driver, string $serverVersion, string $expectedStoreClass)
    {
        if (!class_exists(Connection::class)) {
            $this->markTestSkipped('The "doctrine/dbal" package is required.');
        }

        $connection = DriverManager::getConnection(['driver' => $driver, 'serverVersion' => $serverVersion]);

        $this->assertInstanceOf($expectedStoreClass, StoreFactory::createStore($connection, true));
    }

    public static function advisoryDbalPlatforms(): \Generator
    {
        yield 'PostgreSQL' => ['pdo_pgsql', '16.0', DoctrineDbalPostgreSqlStore::class];
        yield 'MySQL' => ['pdo_mysql', '8.0.0', DoctrineDbalMysqlStore::class];
        yield 'MariaDB' => ['pdo_mysql', '10.6.0-MariaDB', DoctrineDbalMysqlStore::class];
    }

    public function testCreateAdvisoryStoreRejectsUnsupportedDbalPlatform()
    {
        if (!class_exists(Connection::class)) {
            $this->markTestSkipped('The "doctrine/dbal" package is required.');
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" platform does not support advisory locks.', $connection->getDatabasePlatform()::class));

        StoreFactory::createStore($connection, true);
    }

    private function createPdoStub(string $driver): \PDO
    {
        $pdo = $this->createStub(\PDO::class);
        $pdo->method('getAttribute')->willReturnMap([
            [\PDO::ATTR_DRIVER_NAME, $driver],
            [\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION],
        ]);

        return $pdo;
    }

    #[RequiresPhpExtension('sysvsem')]
    public function testCreateSemaphoreStoreDecodesProjectId()
    {
        $store = StoreFactory::createStore('semaphore://my%20project%2Fid');

        $this->assertInstanceOf(SemaphoreStore::class, $store);
        $this->assertSame('my project/id', (new \ReflectionProperty(SemaphoreStore::class, 'projectId'))->getValue($store));
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
            yield ['semaphore://project-id', SemaphoreStore::class];
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
            yield ['mysql+advisory:host=localhost;dbname=test;', MysqlStore::class];
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
            yield ['mysql+advisory://server.com/test', DoctrineDbalMysqlStore::class];
            yield ['mysql2+advisory://server.com/test', DoctrineDbalMysqlStore::class];
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
}

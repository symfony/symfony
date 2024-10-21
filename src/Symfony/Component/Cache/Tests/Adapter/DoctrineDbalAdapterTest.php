<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\Schema;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Tests\Fixtures\DriverWrapper;

/**
 * @requires extension pdo_sqlite
 *
 * @group time-sensitive
 */
class DoctrineDbalAdapterTest extends AdapterTestCase
{
    protected static $dbFile;

    public static function setUpBeforeClass(): void
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new DoctrineDbalAdapter(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $this->getDbalConfig()), '', $defaultLifetime);
    }

    public function testConfigureSchemaDecoratedDbalDriver()
    {
        if (file_exists(self::$dbFile)) {
            @unlink(self::$dbFile);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $this->getDbalConfig());
        if (!interface_exists(Middleware::class)) {
            $this->markTestSkipped('doctrine/dbal v2 does not support custom drivers using middleware');
        }

        $middleware = $this->createMock(Middleware::class);
        $middleware
            ->method('wrap')
            ->willReturn(new DriverWrapper($connection->getDriver()));

        $config = $this->getDbalConfig();
        $config->setMiddlewares([$middleware]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config);

        $adapter = new DoctrineDbalAdapter($connection);
        $adapter->createTable();

        $item = $adapter->getItem('key');
        $item->set('value');
        $this->assertTrue($adapter->save($item));
    }

    public function testConfigureSchema()
    {
        if (file_exists(self::$dbFile)) {
            @unlink(self::$dbFile);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $this->getDbalConfig());
        $schema = new Schema();

        $adapter = new DoctrineDbalAdapter($connection);
        $adapter->configureSchema($schema, $connection);
        $this->assertTrue($schema->hasTable('cache_items'));
    }

    public function testConfigureSchemaDifferentDbalConnection()
    {
        if (file_exists(self::$dbFile)) {
            @unlink(self::$dbFile);
        }

        $otherConnection = $this->createConnectionMock();
        $schema = new Schema();

        $adapter = $this->createCachePool();
        $adapter->configureSchema($schema, $otherConnection);
        $this->assertFalse($schema->hasTable('cache_items'));
    }

    public function testConfigureSchemaTableExists()
    {
        if (file_exists(self::$dbFile)) {
            @unlink(self::$dbFile);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $this->getDbalConfig());
        $schema = new Schema();
        $schema->createTable('cache_items');

        $adapter = new DoctrineDbalAdapter($connection);
        $adapter->configureSchema($schema, $connection);
        $table = $schema->getTable('cache_items');
        $this->assertEmpty($table->getColumns(), 'The table was not overwritten');
    }

    /**
     * @dataProvider provideDsnWithSQLite
     */
    public function testDsnWithSQLite(string $dsn, ?string $file = null)
    {
        try {
            $pool = new DoctrineDbalAdapter($dsn);

            $item = $pool->getItem('key');
            $item->set('value');
            $this->assertTrue($pool->save($item));
        } finally {
            if (null !== $file) {
                @unlink($file);
            }
        }
    }

    public static function provideDsnWithSQLite()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        yield 'SQLite file' => ['sqlite://localhost/'.$dbFile.'1', $dbFile.'1'];
        yield 'SQLite3 file' => ['sqlite3:///'.$dbFile.'3', $dbFile.'3'];
        yield 'SQLite in memory' => ['sqlite://localhost/:memory:'];
    }

    /**
     * @requires extension pdo_pgsql
     *
     * @group integration
     */
    public function testDsnWithPostgreSQL()
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        try {
            $pool = new DoctrineDbalAdapter('pgsql://postgres:password@'.$host);

            $item = $pool->getItem('key');
            $item->set('value');
            $this->assertTrue($pool->save($item));
        } finally {
            $pdo = new \PDO('pgsql:host='.$host.';user=postgres;password=password');
            $pdo->exec('DROP TABLE IF EXISTS cache_items');
        }
    }

    protected function isPruned(DoctrineDbalAdapter $cache, string $name): bool
    {
        $o = new \ReflectionObject($cache);
        $connProp = $o->getProperty('conn');
        $connProp->setAccessible(true);

        /** @var Connection $conn */
        $conn = $connProp->getValue($cache);
        $result = $conn->executeQuery('SELECT 1 FROM cache_items WHERE item_id LIKE ?', [sprintf('%%%s', $name)]);

        return 1 !== (int) $result->fetchOne();
    }

    private function createConnectionMock()
    {
        $connection = $this->createMock(Connection::class);
        $driver = $this->createMock(AbstractMySQLDriver::class);
        $connection->expects($this->any())
            ->method('getDriver')
            ->willReturn($driver);

        return $connection;
    }

    private function getDbalConfig()
    {
        $config = new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        return $config;
    }
}

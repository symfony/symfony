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
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\SkippedTestSuiteError;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Tests\Fixtures\DriverWrapper;

/**
 * @group time-sensitive
 */
class PdoDbalAdapterTest extends AdapterTestCase
{
    use PdoPruneableTrait;

    protected static $dbFile;

    public static function setUpBeforeClass(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            throw new SkippedTestSuiteError('Extension pdo_sqlite required.');
        }

        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new PdoAdapter(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]), '', $defaultLifetime);
    }

    public function testConfigureSchemaDecoratedDbalDriver()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]);
        if (!interface_exists(Middleware::class)) {
            $this->markTestSkipped('doctrine/dbal v2 does not support custom drivers using middleware');
        }

        $middleware = $this->createMock(Middleware::class);
        $middleware
            ->method('wrap')
            ->willReturn(new DriverWrapper($connection->getDriver()));

        $config = new Configuration();
        $config->setMiddlewares([$middleware]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config);

        $adapter = new PdoAdapter($connection);
        $adapter->createTable();

        $item = $adapter->getItem('key');
        $item->set('value');
        $this->assertTrue($adapter->save($item));
    }

    public function testConfigureSchema()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]);
        $schema = new Schema();

        $adapter = new PdoAdapter($connection);
        $adapter->configureSchema($schema, $connection);
        $this->assertTrue($schema->hasTable('cache_items'));
    }

    public function testConfigureSchemaDifferentDbalConnection()
    {
        $otherConnection = $this->createConnectionMock();
        $schema = new Schema();

        $adapter = $this->createCachePool();
        $adapter->configureSchema($schema, $otherConnection);
        $this->assertFalse($schema->hasTable('cache_items'));
    }

    public function testConfigureSchemaTableExists()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]);
        $schema = new Schema();
        $schema->createTable('cache_items');

        $adapter = new PdoAdapter($connection);
        $adapter->configureSchema($schema, $connection);
        $table = $schema->getTable('cache_items');
        $this->assertEmpty($table->getColumns(), 'The table was not overwritten');
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
}

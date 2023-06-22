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
use PHPUnit\Framework\SkippedTestSuiteError;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Tests\Fixtures\DriverWrapper;

/**
 * @group time-sensitive
 * @group legacy
 */
class PdoDbalAdapterTest extends AdapterTestCase
{
    use ExpectDeprecationTrait;

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
        $this->expectDeprecation('Since symfony/cache 5.4: Usage of a DBAL Connection with "Symfony\Component\Cache\Adapter\PdoAdapter" is deprecated and will be removed in symfony 6.0. Use "Symfony\Component\Cache\Adapter\DoctrineDbalAdapter" instead.');
        $config = $this->getDbalConfig();

        return new PdoAdapter(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config), '', $defaultLifetime);
    }

    public function testConfigureSchemaDecoratedDbalDriver()
    {
        $this->expectDeprecation('Since symfony/cache 5.4: Usage of a DBAL Connection with "Symfony\Component\Cache\Adapter\PdoAdapter" is deprecated and will be removed in symfony 6.0. Use "Symfony\Component\Cache\Adapter\DoctrineDbalAdapter" instead.');
        $config = $this->getDbalConfig();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config);
        if (!interface_exists(Middleware::class)) {
            $this->markTestSkipped('doctrine/dbal v2 does not support custom drivers using middleware');
        }

        $middleware = $this->createMock(Middleware::class);
        $middleware
            ->method('wrap')
            ->willReturn(new DriverWrapper($connection->getDriver()));

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
        $this->expectDeprecation('Since symfony/cache 5.4: Usage of a DBAL Connection with "Symfony\Component\Cache\Adapter\PdoAdapter" is deprecated and will be removed in symfony 6.0. Use "Symfony\Component\Cache\Adapter\DoctrineDbalAdapter" instead.');
        $config = $this->getDbalConfig();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config);
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
        $this->expectDeprecation('Since symfony/cache 5.4: Usage of a DBAL Connection with "Symfony\Component\Cache\Adapter\PdoAdapter" is deprecated and will be removed in symfony 6.0. Use "Symfony\Component\Cache\Adapter\DoctrineDbalAdapter" instead.');
        $config = $this->getDbalConfig();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config);
        $schema = new Schema();
        $schema->createTable('cache_items');

        $adapter = new PdoAdapter($connection);
        $adapter->configureSchema($schema, $connection);
        $table = $schema->getTable('cache_items');
        $this->assertEmpty($table->getColumns(), 'The table was not overwritten');
    }

    /**
     * @dataProvider provideDsn
     */
    public function testDsn(string $dsn, string $file = null)
    {
        $this->expectDeprecation('Since symfony/cache 5.4: Usage of a DBAL Connection with "Symfony\Component\Cache\Adapter\PdoAdapter" is deprecated and will be removed in symfony 6.0. Use "Symfony\Component\Cache\Adapter\DoctrineDbalAdapter" instead.');
        try {
            $pool = new PdoAdapter($dsn);
            $pool->createTable();

            $item = $pool->getItem('key');
            $item->set('value');
            $this->assertTrue($pool->save($item));
        } finally {
            if (null !== $file) {
                @unlink($file);
            }
        }
    }

    public static function provideDsn()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        yield ['sqlite://localhost/'.$dbFile.'1', $dbFile.'1'];
        yield ['sqlite3:///'.$dbFile.'3', $dbFile.'3'];
        yield ['sqlite://localhost/:memory:'];
    }

    protected function isPruned(PdoAdapter $cache, string $name): bool
    {
        $dbalAdapterProp = (new \ReflectionObject($cache))->getProperty('dbalAdapter');
        $dbalAdapterProp->setAccessible(true);
        $dbalAdapter = $dbalAdapterProp->getValue($cache);

        $connProp = (new \ReflectionObject($dbalAdapter))->getProperty('conn');
        $connProp->setAccessible(true);

        /** @var Connection $conn */
        $conn = $connProp->getValue($dbalAdapter);
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

    private function getDbalConfig(): Configuration
    {
        $config = new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        return $config;
    }
}

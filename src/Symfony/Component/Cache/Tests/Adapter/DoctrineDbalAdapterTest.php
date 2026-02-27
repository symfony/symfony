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
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;

#[RequiresPhpExtension('pdo_sqlite')]
#[Group('time-sensitive')]
class DoctrineDbalAdapterTest extends AdapterTestCase
{
    protected static string $dbFile;

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

        $middleware = $this->createStub(Middleware::class);
        $middleware
            ->method('wrap')
            ->willReturn(new class($connection->getDriver()) extends AbstractDriverMiddleware {});

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
        $adapter->configureSchema($schema, $connection, static fn () => true);
        $this->assertTrue($schema->hasTable('cache_items'));
    }

    public function testConfigureSchemaDifferentDbalConnection()
    {
        if (file_exists(self::$dbFile)) {
            @unlink(self::$dbFile);
        }

        $otherConnection = $this->createConnection();
        $schema = new Schema();

        $adapter = $this->createCachePool();
        $adapter->configureSchema($schema, $otherConnection, static fn () => false);
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
        $adapter->configureSchema($schema, $connection, static fn () => true);
        $table = $schema->getTable('cache_items');
        $this->assertSame([], $table->getColumns(), 'The table was not overwritten');
    }

    #[DataProvider('provideDsnWithSQLite')]
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

    #[RequiresPhpExtension('pdo_pgsql')]
    #[Group('integration')]
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

    public function testSaveWithinActiveTransactionUsesSavepoint()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_savepoint');
        try {
            $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbFile], $this->getDbalConfig());
            $adapter = new DoctrineDbalAdapter($connection);
            $adapter->createTable();

            $connection->beginTransaction();
            $item = $adapter->getItem('savepoint_key');
            $item->set('savepoint_value');
            $adapter->save($item);

            $this->assertTrue($connection->isTransactionActive(), 'Outer transaction must still be active after cache save');
            $connection->commit();

            $this->assertSame('savepoint_value', $adapter->getItem('savepoint_key')->get());
        } finally {
            @unlink($dbFile);
        }
    }

    public function testSavepointIsRolledBackOnFailure()
    {
        $platform = $this->createStub(AbstractPlatform::class);
        $platform->method('supportsSavepoints')->willReturn(true);

        $conn = $this->createMock(Connection::class);
        $conn->method('isTransactionActive')->willReturn(true);
        $conn->method('getDatabasePlatform')->willReturn($platform);
        $conn->expects($this->once())->method('createSavepoint')->with($this->stringStartsWith('cache_save_'));
        $conn->expects($this->once())->method('rollbackSavepoint')->with($this->stringStartsWith('cache_save_'));
        $conn->expects($this->never())->method('releaseSavepoint');
        $conn->method('prepare')->willThrowException(new \RuntimeException('DB error'));

        $adapter = new DoctrineDbalAdapter($conn);

        $doSave = new \ReflectionMethod($adapter, 'doSave');

        $this->expectException(\RuntimeException::class);
        $doSave->invoke($adapter, ['key' => 'value'], 0);
    }

    protected function isPruned(DoctrineDbalAdapter $cache, string $name): bool
    {
        $o = new \ReflectionObject($cache);
        $connProp = $o->getProperty('conn');

        /** @var Connection $conn */
        $conn = $connProp->getValue($cache);
        $result = $conn->executeQuery('SELECT 1 FROM cache_items WHERE item_id LIKE ?', [\sprintf('%%%s', $name)]);

        return 1 !== (int) $result->fetchOne();
    }

    private function createConnection(): Connection
    {
        $connection = $this->createStub(Connection::class);
        $driver = $this->createStub(AbstractMySQLDriver::class);
        $connection
            ->method('getDriver')
            ->willReturn($driver);

        return $connection;
    }

    private function getDbalConfig(): Configuration
    {
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        return $config;
    }
}

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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

class_exists(\Doctrine\DBAL\Platforms\PostgreSqlPlatform::class);

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension pdo_sqlite
 */
class DoctrineDbalStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    protected static $dbFile;

    public static function setUpBeforeClass(): void
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_lock');

        $store = new DoctrineDbalStore(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]));
        $store->createTable();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 1000000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore(): PersistingStoreInterface
    {
        return new DoctrineDbalStore(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]));
    }

    public function testAbortAfterExpiration()
    {
        $this->markTestSkipped('Pdo expects a TTL greater than 1 sec. Simulating a slow network is too hard');
    }

    /**
     * @dataProvider provideDsn
     */
    public function testDsn(string $dsn, string $file = null)
    {
        $key = new Key(uniqid(__METHOD__, true));

        try {
            $store = new DoctrineDbalStore($dsn);

            $store->save($key);
            $this->assertTrue($store->exists($key));
        } finally {
            if (null !== $file) {
                @unlink($file);
            }
        }
    }

    public function provideDsn()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        yield ['sqlite://localhost/'.$dbFile.'1', $dbFile.'1'];
        yield ['sqlite3:///'.$dbFile.'3', $dbFile.'3'];
        yield ['sqlite://localhost/:memory:'];
    }

    /**
     * @dataProvider providePlatforms
     */
    public function testCreatesTableInTransaction(string $platform)
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects($this->atLeast(3))
            ->method('executeStatement')
            ->withConsecutive(
                [$this->stringContains('INSERT INTO')],
                [$this->matches('create sql stmt')],
                [$this->stringContains('INSERT INTO')]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->throwException(
                        $this->createMock(TableNotFoundException::class)
                    ),
                    1,
                    1
                )
            );

        $conn->method('isTransactionActive')
            ->willReturn(true);

        $platform = $this->createMock($platform);
        $platform->method(method_exists(AbstractPlatform::class, 'getCreateTablesSQL') ? 'getCreateTablesSQL' : 'getCreateTableSQL')
            ->willReturn(['create sql stmt']);

        $conn->method('getDatabasePlatform')
            ->willReturn($platform);

        $store = new DoctrineDbalStore($conn);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
    }

    public function providePlatforms()
    {
        yield [\Doctrine\DBAL\Platforms\PostgreSQLPlatform::class];
        yield [\Doctrine\DBAL\Platforms\PostgreSQL94Platform::class];
        yield [\Doctrine\DBAL\Platforms\SqlitePlatform::class];
        yield [\Doctrine\DBAL\Platforms\SQLServerPlatform::class];
        yield [\Doctrine\DBAL\Platforms\SQLServer2012Platform::class];
    }

    public function testTableCreationInTransactionNotSupported()
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects($this->atLeast(2))
            ->method('executeStatement')
            ->withConsecutive(
                [$this->stringContains('INSERT INTO')],
                [$this->stringContains('INSERT INTO')]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->throwException(
                        $this->createMock(TableNotFoundException::class)
                    ),
                    1,
                    1
                )
            );

        $conn->method('isTransactionActive')
            ->willReturn(true);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method(method_exists(AbstractPlatform::class, 'getCreateTablesSQL') ? 'getCreateTablesSQL' : 'getCreateTableSQL')
            ->willReturn(['create sql stmt']);

        $conn->expects($this->atLeast(2))
            ->method('getDatabasePlatform');

        $store = new DoctrineDbalStore($conn);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
    }

    public function testCreatesTableOutsideTransaction()
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects($this->atLeast(3))
            ->method('executeStatement')
            ->withConsecutive(
                [$this->stringContains('INSERT INTO')],
                [$this->matches('create sql stmt')],
                [$this->stringContains('INSERT INTO')]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->throwException(
                        $this->createMock(TableNotFoundException::class)
                    ),
                    1,
                    1
                )
            );

        $conn->method('isTransactionActive')
            ->willReturn(false);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method(method_exists(AbstractPlatform::class, 'getCreateTablesSQL') ? 'getCreateTablesSQL' : 'getCreateTableSQL')
            ->willReturn(['create sql stmt']);

        $conn->method('getDatabasePlatform')
            ->willReturn($platform);

        $store = new DoctrineDbalStore($conn);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
    }
}

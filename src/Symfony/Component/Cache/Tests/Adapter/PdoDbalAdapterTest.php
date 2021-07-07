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
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\SkippedTestSuiteError;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;

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
        if (interface_exists(\Doctrine\DBAL\Driver\Middleware::class, false)) {
            $middleware = $this->createMock(\Doctrine\DBAL\Driver\Middleware::class);
            $middleware
                ->method('wrap')
                ->willReturn(new DriverWrapper($connection->getDriver()));

            $config = new Configuration();
            $config->setMiddlewares([$middleware]);

            $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config);
        } else {
            $reflectionProperty = new \ReflectionProperty($connection, '_driver');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($connection, new DriverWrapper($reflectionProperty->getValue($connection)));
            $reflectionProperty->setAccessible(false);
        }

        $adapter = new PdoAdapter($connection);
        $adapter->createTable();

        $item = $adapter->getItem('key');
        $item->set('value');
        $this->assertTrue($adapter->save($item));
    }
}

class DriverWrapper implements Driver
{
    /** @var Driver $driver */
    private $driver;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        return $this->driver->connect($params, $username, $password, $driverOptions);
    }

    public function getDatabasePlatform()
    {
        return $this->driver->getDatabasePlatform();
    }

    public function getSchemaManager(Connection $conn)
    {
        return $this->driver->getSchemaManager($conn);
    }

    public function getName()
    {
        return $this->driver->getName();
    }

    public function getDatabase(Connection $conn)
    {
        return $this->driver->getDatabase($conn);
    }
}

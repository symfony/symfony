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
use Doctrine\DBAL\Platforms\AbstractPlatform;
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
        if (interface_exists(\Doctrine\DBAL\Driver\Middleware::class)) {
            $middleware = $this->createMock(\Doctrine\DBAL\Driver\Middleware::class);
            $middleware
                ->method('wrap')
                ->willReturn(new DriverWrapperV3($connection->getDriver()));

            $config = new Configuration();
            $config->setMiddlewares([$middleware]);

            $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config);
        } else {
            $reflectionProperty = new \ReflectionProperty($connection, '_driver');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($connection, new DriverWrapperV2($reflectionProperty->getValue($connection)));
            $reflectionProperty->setAccessible(false);
        }

        $adapter = new PdoAdapter($connection);
        $adapter->createTable();

        $item = $adapter->getItem('key');
        $item->set('value');
        $this->assertTrue($adapter->save($item));
    }
}

if (interface_exists(\Doctrine\DBAL\Driver\Middleware::class)) {
    class DriverWrapperV3 implements Driver
    {
        /** @var Driver */
        private $driver;

        public function __construct(Driver $driver)
        {
            $this->driver = $driver;
        }

        /**
         * @return Driver\Connection
         */
        public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
        {
            return $this->driver->connect($params, $username, $password, $driverOptions);
        }

        /**
         * @return \Doctrine\DBAL\Platforms\AbstractPlatform
         */
        public function getDatabasePlatform()
        {
            return $this->driver->getDatabasePlatform();
        }

        /**
         * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
         */
        public function getSchemaManager(Connection $conn, AbstractPlatform $platform)
        {
            return $this->driver->getSchemaManager($conn, $platform);
        }

        /**
         * @return \Doctrine\DBAL\Driver\API\ExceptionConverter
         */
        public function getExceptionConverter()
        {
            return $this->driver->getExceptionConverter();
        }
    }
} else {
    class DriverWrapperV2 implements Driver
    {
        /** @var Driver */
        private $driver;

        public function __construct(Driver $driver)
        {
            $this->driver = $driver;
        }

        /**
         * @return Driver\Connection
         */
        public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
        {
            return $this->driver->connect($params, $username, $password, $driverOptions);
        }

        /**
         * @return \Doctrine\DBAL\Platforms\AbstractPlatform
         */
        public function getDatabasePlatform()
        {
            return $this->driver->getDatabasePlatform();
        }

        /**
         * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
         */
        public function getSchemaManager(Connection $conn)
        {
            return $this->driver->getSchemaManager($conn);
        }

        /**
         * @return string
         */
        public function getName()
        {
            return $this->driver->getName();
        }

        /**
         * @return string
         */
        public function getDatabase(Connection $conn)
        {
            return $this->driver->getDatabase($conn);
        }
    }
}

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

use Doctrine\DBAL\DriverManager;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\MysqlStore;

/**
 * @author Jérôme TAMARELLE <jerome@tamarelle.net>
 */
class MysqlStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;

    private $connectionCase = 'pdo';

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        switch ($this->connectionCase) {
            case 'pdo':
                $connection = new \PDO('mysql:host='.getenv('MYSQL_HOST'), getenv('MYSQL_USERNAME'), getenv('MYSQL_PASSWORD'));
                $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                break;

            case 'dbal':
                $connection = DriverManager::getConnection(array(
                    'driver' => 'pdo_mysql',
                    'user' => getenv('MYSQL_USERNAME'),
                    'password' => getenv('MYSQL_PASSWORD'),
                    'host' => getenv('MYSQL_HOST'),
                ));
                break;
        }

        return new MysqlStore($connection);
    }

    public function testSaveWithDoctrineDBAL()
    {
        if (!class_exists(DriverManager::class)) {
            $this->markTestSkipped('Package doctrine/dbal is required.');
        }

        $this->connectionCase = 'dbal';

        parent::testSave();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Symfony\Component\Lock\Store\MysqlStore requires a "mysql" connection. "sqlite" given.
     */
    public function testOnlyMySQLDatabaseIsSupported()
    {
        $connection = new \PDO('sqlite::memory:');

        return new MysqlStore($connection);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Symfony\Component\Lock\Store\MysqlStore requires a "pdo_mysql" connection. "pdo_sqlite" given.
     */
    public function testOnlyMySQLDatabaseIsSupportedWithDoctrineDBAL()
    {
        if (!class_exists(DriverManager::class)) {
            $this->markTestSkipped('Package doctrine/dbal is required.');
        }

        $connection = DriverManager::getConnection(array(
            'driver' => 'pdo_sqlite',
        ));

        return new MysqlStore($connection);
    }

    /**
     * @expectedException \Symfony\Component\Lock\Exception\LockAcquiringException
     * @expectedExceptionMessage Lock already acquired with the same MySQL connection.
     */
    public function testWaitTheSameResourceOnTheSameConnectionIsNotSupported()
    {
        $store = $this->getStore();

        $resource = uniqid(__METHOD__, true);
        $key1 = new Key($resource);
        $key2 = new Key($resource);

        $store->save($key1);
        $store->waitAndSave($key2);
    }
}

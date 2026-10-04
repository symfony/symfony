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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
#[RequiresPhpExtension('pdo_pgsql')]
#[Group('integration')]
class DoctrineDbalPostgreSqlStoreTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;
    use SharedLockStoreTestTrait;

    public function createPostgreSqlConnection(): Connection
    {
        if (!getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        return self::getDbalConnection('pdo-pgsql://postgres:password@'.getenv('POSTGRES_HOST'));
    }

    public function getStore(): PersistingStoreInterface
    {
        $conn = $this->createPostgreSqlConnection();

        return new DoctrineDbalPostgreSqlStore($conn);
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    #[DataProvider('getInvalidDrivers')]
    public function testInvalidDriver($connOrDsn)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The adapter "Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore" does not support');

        $store = new DoctrineDbalPostgreSqlStore($connOrDsn);
        $store->exists(new Key('foo'));
    }

    public static function getInvalidDrivers()
    {
        yield ['sqlite:///tmp/foo.db'];
        yield [self::getDbalConnection('sqlite:///tmp/foo.db')];
    }

    public function testSaveAfterConflict()
    {
        $store1 = $this->getStore();
        $store2 = $this->getStore();

        $key = new Key(__METHOD__);

        $store1->save($key);
        $this->assertTrue($store1->exists($key));

        $lockConflicted = false;
        try {
            $store2->save($key);
        } catch (LockConflictedException $lockConflictedException) {
            $lockConflicted = true;
        }

        $this->assertTrue($lockConflicted);
        $this->assertFalse($store2->exists($key));

        $store1->delete($key);

        $store2->save($key);
        $this->assertTrue($store2->exists($key));
    }

    public function testWaitAndSaveAfterConflictReleasesLockFromInternalStore()
    {
        $store1 = $this->getStore();
        $conn = $this->createPostgreSqlConnection();
        $store2 = new DoctrineDbalPostgreSqlStore($conn);

        $store1Key = new Key(__METHOD__);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        $conn->executeStatement('SET statement_timeout = 1');
        $waitSaveError = null;
        try {
            $store2->waitAndSave(new Key(__METHOD__));
        } catch (DBALException $waitSaveError) {
        }
        $this->assertInstanceOf(DBALException::class, $waitSaveError, 'waitAndSave should have thrown');
        $conn->executeStatement('SET statement_timeout = 0');

        $store1->delete($store1Key);
        $this->assertFalse($store1->exists($store1Key));

        $store2Key = new Key(__METHOD__);
        $lockConflicted = false;
        try {
            $store2->waitAndSave($store2Key);
        } catch (LockConflictedException $lockConflictedException) {
            $lockConflicted = true;
        }

        $this->assertFalse($lockConflicted, 'lock should be available now that its been remove from $store1');
        $this->assertTrue($store2->exists($store2Key));
    }

    public function testWaitAndSaveReadAfterConflictReleasesLockFromInternalStore()
    {
        $store1 = $this->getStore();
        $conn = $this->createPostgreSqlConnection();
        $store2 = new DoctrineDbalPostgreSqlStore($conn);

        $store1Key = new Key(__METHOD__);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        $conn->executeStatement('SET statement_timeout = 1');
        $waitSaveError = null;
        try {
            $store2->waitAndSaveRead(new Key(__METHOD__));
        } catch (DBALException $waitSaveError) {
        }
        $this->assertInstanceOf(DBALException::class, $waitSaveError, 'waitAndSaveRead should have thrown');

        $store1->delete($store1Key);
        $this->assertFalse($store1->exists($store1Key));

        $store2Key = new Key(__METHOD__);
        // since the lock is going to be acquired in read mode and is not exclusive
        // this won't every throw a LockConflictedException as it would from
        // waitAndSave, but it will hang indefinitely as it waits for postgres
        // so set a time out of 2 seconds here so the test doesn't just sit forever
        $conn->executeStatement('SET statement_timeout = 2000');
        $store2->waitAndSaveRead($store2Key);

        $this->assertTrue($store2->exists($store2Key));
    }

    public function testFailedPromotionKeepsTheReadLock()
    {
        $store1 = $this->getStore();
        $store2 = $this->getStore();

        $resource = __METHOD__;
        $key1 = new Key($resource);
        $key2 = new Key($resource);

        $store1->saveRead($key1);
        $store2->saveRead($key2);

        try {
            $store1->save($key1);
            $this->fail('The store shouldn\'t promote a read lock shared with another connection');
        } catch (LockConflictedException) {
        }

        $this->assertTrue($store1->exists($key1));

        $store1->delete($key1);
        $store2->save($key2);
        $this->assertTrue($store2->exists($key2));
    }

    public function testFailedBlockingPromotionKeepsTheReadLock()
    {
        $conn = $this->createPostgreSqlConnection();
        $store1 = new DoctrineDbalPostgreSqlStore($conn);
        $store2 = $this->getStore();

        $resource = __METHOD__;
        $key1 = new Key($resource);
        $key2 = new Key($resource);

        $store1->saveRead($key1);
        $store2->saveRead($key2);

        $conn->executeStatement('SET statement_timeout = 1');
        try {
            $store1->waitAndSave($key1);
            $this->fail('The store shouldn\'t promote a read lock shared with another connection');
        } catch (DBALException) {
        }
        $conn->executeStatement('SET statement_timeout = 0');

        $this->assertTrue($store1->exists($key1));

        $store1->delete($key1);
        $store2->save($key2);
        $this->assertTrue($store2->exists($key2));
    }

    #[RequiresPhpExtension('pgsql')]
    public function testFailedDemotionKeepsTheWriteLock()
    {
        $conn = $this->createPostgreSqlConnection();
        $store = new DoctrineDbalPostgreSqlStore($conn);

        $key = new Key(__METHOD__);
        $store->save($key);

        $waiter = pg_connect('host='.getenv('POSTGRES_HOST').' user=postgres password=password', \PGSQL_CONNECT_FORCE_NEW);
        pg_query($waiter, 'SET lock_timeout = 5000');
        pg_send_query($waiter, 'SELECT pg_advisory_lock('.crc32((string) $key).')');
        while (pg_connection_busy($waiter) && !$conn->fetchOne('SELECT count(*) FROM pg_locks WHERE NOT granted AND pid = '.pg_get_pid($waiter))) {
            usleep(10000);
        }

        try {
            $store->saveRead($key);
            $this->fail('The store shouldn\'t demote a write lock while another connection waits for it');
        } catch (LockConflictedException) {
        }

        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertSame(\PGSQL_TUPLES_OK, pg_result_status(pg_get_result($waiter)));
        pg_close($waiter);
    }

    private static function getDbalConnection(string $dsn): Connection
    {
        $params = (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse($dsn);
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        return DriverManager::getConnection($params, $config);
    }
}

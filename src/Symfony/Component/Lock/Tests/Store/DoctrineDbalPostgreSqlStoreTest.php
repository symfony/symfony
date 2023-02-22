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
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension pdo_pgsql
 *
 * @group integration
 */
class DoctrineDbalPostgreSqlStoreTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;
    use SharedLockStoreTestTrait;

    public function createPostgreSqlConnection(): Connection
    {
        if (!getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        return DriverManager::getConnection(['url' => 'pgsql://postgres:password@'.getenv('POSTGRES_HOST')]);
    }

    public function getStore(): PersistingStoreInterface
    {
        $conn = $this->createPostgreSqlConnection();

        return new DoctrineDbalPostgreSqlStore($conn);
    }

    /**
     * @requires extension pdo_sqlite
     *
     * @dataProvider getInvalidDrivers
     */
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
        yield [DriverManager::getConnection(['url' => 'sqlite:///tmp/foo.db'])];
    }

    public function testSaveAfterConflict()
    {
        $store1 = $this->getStore();
        $store2 = $this->getStore();

        $key = new Key(uniqid(__METHOD__, true));

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

        $keyId = uniqid(__METHOD__, true);
        $store1Key = new Key($keyId);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        $conn->executeStatement('SET statement_timeout = 1');
        $waitSaveError = null;
        try {
            $store2->waitAndSave(new Key($keyId));
        } catch (DBALException $waitSaveError) {
        }
        $this->assertInstanceOf(DBALException::class, $waitSaveError, 'waitAndSave should have thrown');
        $conn->executeStatement('SET statement_timeout = 0');

        $store1->delete($store1Key);
        $this->assertFalse($store1->exists($store1Key));

        $store2Key = new Key($keyId);
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

        $keyId = uniqid(__METHOD__, true);
        $store1Key = new Key($keyId);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        $conn->executeStatement('SET statement_timeout = 1');
        $waitSaveError = null;
        try {
            $store2->waitAndSaveRead(new Key($keyId));
        } catch (DBALException $waitSaveError) {
        }
        $this->assertInstanceOf(DBALException::class, $waitSaveError, 'waitAndSaveRead should have thrown');

        $store1->delete($store1Key);
        $this->assertFalse($store1->exists($store1Key));

        $store2Key = new Key($keyId);
        // since the lock is going to be acquired in read mode and is not exclusive
        // this won't every throw a LockConflictedException as it would from
        // waitAndSave, but it will hang indefinitely as it waits for postgres
        // so set a time out of 2 seconds here so the test doesn't just sit forever
        $conn->executeStatement('SET statement_timeout = 2000');
        $store2->waitAndSaveRead($store2Key);

        $this->assertTrue($store2->exists($store2Key));
    }
}

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

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\PostgreSqlStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension pdo_pgsql
 *
 * @group integration
 */
class PostgreSqlStoreTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;
    use SharedLockStoreTestTrait;

    public function getPostgresHost(): string
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        return $host;
    }

    public function getStore(): PersistingStoreInterface
    {
        $host = $this->getPostgresHost();

        return new PostgreSqlStore('pgsql:host='.$host, ['db_username' => 'postgres', 'db_password' => 'password']);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testInvalidDriver()
    {
        $store = new PostgreSqlStore('sqlite:/tmp/foo.db');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The adapter "Symfony\Component\Lock\Store\PostgreSqlStore" does not support');
        $store->exists(new Key('foo'));
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
        $postgresHost = $this->getPostgresHost();
        $pdo = new \PDO('pgsql:host='.$postgresHost, 'postgres', 'password');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $store2 = new PostgreSqlStore($pdo);

        $keyId = uniqid(__METHOD__, true);
        $store1Key = new Key($keyId);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        $pdo->exec('SET statement_timeout = 1');
        $waitSaveError = null;
        try {
            $store2->waitAndSave(new Key($keyId));
        } catch (\PDOException $waitSaveError) {
        }
        $this->assertInstanceOf(\PDOException::class, $waitSaveError, 'waitAndSave should have thrown');
        $pdo->exec('SET statement_timeout = 0');

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
        $postgresHost = $this->getPostgresHost();
        $pdo = new \PDO('pgsql:host='.$postgresHost, 'postgres', 'password');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $store2 = new PostgreSqlStore($pdo);

        $keyId = uniqid(__METHOD__, true);
        $store1Key = new Key($keyId);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        $pdo->exec('SET statement_timeout = 1');
        $waitSaveError = null;
        try {
            $store2->waitAndSaveRead(new Key($keyId));
        } catch (\PDOException $waitSaveError) {
        }
        $this->assertInstanceOf(\PDOException::class, $waitSaveError, 'waitAndSave should have thrown');

        $store1->delete($store1Key);
        $this->assertFalse($store1->exists($store1Key));

        $store2Key = new Key($keyId);
        // since the lock is going to be acquired in read mode and is not exclusive
        // this won't every throw a LockConflictedException as it would from
        // waitAndSave, but it will hang indefinitely as it waits for postgres
        // so set a time out of 2 seconds here so the test doesn't just sit forever
        $pdo->exec('SET statement_timeout = 20000');
        $store2->waitAndSaveRead($store2Key);

        $this->assertTrue($store2->exists($store2Key));
    }
}

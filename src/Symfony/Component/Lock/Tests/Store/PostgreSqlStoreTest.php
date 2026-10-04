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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\PostgreSqlStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
#[RequiresPhpExtension('pdo_pgsql')]
#[Group('integration')]
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

    #[RequiresPhpExtension('pdo_sqlite')]
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
        $postgresHost = $this->getPostgresHost();
        $pdo = new \PDO('pgsql:host='.$postgresHost, 'postgres', 'password');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $store2 = new PostgreSqlStore($pdo);

        $store1Key = new Key(__METHOD__);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        $pdo->exec('SET statement_timeout = 1');
        $waitSaveError = null;
        try {
            $store2->waitAndSave(new Key(__METHOD__));
        } catch (\PDOException $waitSaveError) {
        }
        $this->assertInstanceOf(\PDOException::class, $waitSaveError, 'waitAndSave should have thrown');
        $pdo->exec('SET statement_timeout = 0');

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
        $postgresHost = $this->getPostgresHost();
        $pdo = new \PDO('pgsql:host='.$postgresHost, 'postgres', 'password');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $store2 = new PostgreSqlStore($pdo);

        $store1Key = new Key(__METHOD__);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        $pdo->exec('SET statement_timeout = 1');
        $waitSaveError = null;
        try {
            $store2->waitAndSaveRead(new Key(__METHOD__));
        } catch (\PDOException $waitSaveError) {
        }
        $this->assertInstanceOf(\PDOException::class, $waitSaveError, 'waitAndSave should have thrown');

        $store1->delete($store1Key);
        $this->assertFalse($store1->exists($store1Key));

        $store2Key = new Key(__METHOD__);
        // since the lock is going to be acquired in read mode and is not exclusive
        // this won't every throw a LockConflictedException as it would from
        // waitAndSave, but it will hang indefinitely as it waits for postgres
        // so set a time out of 2 seconds here so the test doesn't just sit forever
        $pdo->exec('SET statement_timeout = 20000');
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
        $pdo = new \PDO('pgsql:host='.$this->getPostgresHost(), 'postgres', 'password');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $store1 = new PostgreSqlStore($pdo);
        $store2 = $this->getStore();

        $resource = __METHOD__;
        $key1 = new Key($resource);
        $key2 = new Key($resource);

        $store1->saveRead($key1);
        $store2->saveRead($key2);

        $pdo->exec('SET statement_timeout = 1');
        try {
            $store1->waitAndSave($key1);
            $this->fail('The store shouldn\'t promote a read lock shared with another connection');
        } catch (\PDOException) {
        }
        $pdo->exec('SET statement_timeout = 0');

        $this->assertTrue($store1->exists($key1));

        $store1->delete($key1);
        $store2->save($key2);
        $this->assertTrue($store2->exists($key2));
    }

    #[RequiresPhpExtension('pgsql')]
    public function testFailedDemotionKeepsTheWriteLock()
    {
        $pdo = new \PDO('pgsql:host='.$this->getPostgresHost(), 'postgres', 'password');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $store = new PostgreSqlStore($pdo);

        $key = new Key(__METHOD__);
        $store->save($key);

        $waiter = pg_connect('host='.$this->getPostgresHost().' user=postgres password=password', \PGSQL_CONNECT_FORCE_NEW);
        pg_query($waiter, 'SET lock_timeout = 5000');
        pg_send_query($waiter, 'SELECT pg_advisory_lock('.crc32((string) $key).')');
        while (pg_connection_busy($waiter) && !$pdo->query('SELECT count(*) FROM pg_locks WHERE NOT granted AND pid = '.pg_get_pid($waiter))->fetchColumn()) {
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
}

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

use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\PdoStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension pdo_sqlite
 */
class PdoStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;

    protected static string $dbFile;

    public static function setUpBeforeClass(): void
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_lock');

        $store = new PdoStore('sqlite:'.self::$dbFile);
        $store->createTable();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    protected function getClockDelay(): int
    {
        return 1000000;
    }

    public function getStore(): PersistingStoreInterface
    {
        return new PdoStore('sqlite:'.self::$dbFile);
    }

    public function testAbortAfterExpiration()
    {
        $this->markTestSkipped('Pdo expects a TTL greater than 1 sec. Simulating a slow network is too hard');
    }

    public function testInvalidTtl()
    {
        $this->expectException(InvalidTtlException::class);
        $store = $this->getStore();
        $store->putOffExpiration(new Key('toto'), 0.1);
    }

    public function testInvalidTtlConstruct()
    {
        $this->expectException(InvalidTtlException::class);

        return new PdoStore('sqlite:'.self::$dbFile, [], 0.1, 0);
    }

    /**
     * @dataProvider provideDsnWithSQLite
     */
    public function testDsnWithSQLite(string $dsn, ?string $file = null)
    {
        $key = new Key(uniqid(__METHOD__, true));

        try {
            $store = new PdoStore($dsn);

            $store->save($key);
            $this->assertTrue($store->exists($key));
        } finally {
            if (null !== $file) {
                @unlink($file);
            }
        }
    }

    public static function provideDsnWithSQLite()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        yield 'SQLite file' => ['sqlite:'.$dbFile.'2', $dbFile.'2'];
        yield 'SQLite in memory' => ['sqlite::memory:'];
    }

    /**
     * @requires extension pdo_pgsql
     *
     * @group integration
     */
    public function testDsnWithPostgreSQL()
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $key = new Key(uniqid(__METHOD__, true));

        $dsn = 'pgsql:host='.$host.';user=postgres;password=password';

        try {
            $store = new PdoStore($dsn);

            $store->save($key);
            $this->assertTrue($store->exists($key));
        } finally {
            $pdo = new \PDO($dsn);
            $pdo->exec('DROP TABLE IF EXISTS lock_keys');
        }
    }

    /**
     * @requires extension pdo_pgsql
     *
     * @group integration
     */
    public function testSaveDoesNotAbortSurroundingPostgresTransactionOnLockContention()
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $dsn = 'pgsql:host='.$host.';user=postgres;password=password';
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $resource = uniqid(__METHOD__, true);

        try {
            $store = new PdoStore($pdo);
            $store->createTable();

            $owner = new Key($resource);
            $store->save($owner);

            $pdo->beginTransaction();

            $contender = new Key($resource);
            try {
                $store->save($contender);
                $this->fail('LockConflictedException was expected.');
            } catch (LockConflictedException) {
                // expected
            }

            $this->assertSame('alive', $pdo->query("SELECT 'alive'")->fetchColumn(), 'The surrounding transaction must remain usable after a lock conflict.');
            $pdo->rollBack();
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('DROP TABLE IF EXISTS lock_keys');
        }
    }

    /**
     * @requires extension pdo_pgsql
     *
     * @group integration
     */
    public function testSavePostgresRefreshesSameKeyInsideTransaction()
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $dsn = 'pgsql:host='.$host.';user=postgres;password=password';
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        try {
            $store = new PdoStore($pdo);
            $store->createTable();

            $key = new Key(uniqid(__METHOD__, true));
            $store->save($key);

            $pdo->beginTransaction();
            $store->save($key);
            $this->assertTrue($store->exists($key));
            $pdo->rollBack();
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('DROP TABLE IF EXISTS lock_keys');
        }
    }

    /**
     * @requires extension pdo_mysql
     *
     * @group integration
     *
     * @dataProvider provideDaylightSavingTimeChanges
     */
    public function testMysqlLockSurvivesDaylightSavingTimeChange(int $holderTime, int $contenderTime, bool $holderKeepsLock)
    {
        if (!$host = getenv('MYSQL_HOST')) {
            $this->markTestSkipped('Missing MYSQL_HOST env variable');
        }

        $pdo = new \PDO('mysql:host='.$host, getenv('MYSQL_USERNAME') ?: 'root', getenv('MYSQL_PASSWORD') ?: '');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->exec("SET time_zone = 'America/Chicago'");
        } catch (\PDOException) {
            $this->markTestSkipped('MySQL time zone tables are not loaded');
        }
        $pdo->exec('CREATE DATABASE IF NOT EXISTS symfony_lock_test');
        $pdo->exec('USE symfony_lock_test');

        $store = new PdoStore($pdo, [], 0.0);
        $holder = new Key(__METHOD__);
        $contender = new Key(__METHOD__);

        try {
            $pdo->exec('SET TIMESTAMP = '.$holderTime);
            $store->save($holder);

            $pdo->exec('SET TIMESTAMP = '.$contenderTime);
            try {
                $store->save($contender);
            } catch (LockConflictedException) {
            }

            $this->assertSame($holderKeepsLock, $store->exists($holder));
            $this->assertSame(!$holderKeepsLock, $store->exists($contender));
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS lock_keys');
        }
    }

    public static function provideDaylightSavingTimeChanges(): iterable
    {
        // On 2025-11-02, America/Chicago went from 02:00 CDT back to 01:00 CST at 07:00 UTC, so 01:00-02:00 local happened twice.
        yield 'lock taken before the first 01:00' => [gmmktime(5, 58, 0, 11, 2, 2025), gmmktime(6, 0, 30, 11, 2, 2025), true];
        yield 'lock taken before 02:00 CST' => [gmmktime(7, 58, 0, 11, 2, 2025), gmmktime(8, 0, 30, 11, 2, 2025), true];
        yield 'lock expired after the second 01:00' => [gmmktime(6, 58, 0, 11, 2, 2025), gmmktime(7, 4, 0, 11, 2, 2025), false];
    }
}

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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\PdoStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
#[RequiresPhpExtension('pdo_sqlite')]
class PdoStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;
    use MysqlDaylightSavingTimeTestTrait;

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

    protected function createMysqlStore(array $params): array
    {
        $pdo = new \PDO('mysql:host='.$params['host'].';dbname='.$params['dbname'], $params['user'], $params['password']);

        return [new PdoStore($pdo, [], 0.0), $pdo->exec(...)];
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

    #[DataProvider('provideDsnWithSQLite')]
    public function testDsnWithSQLite(string $dsn, ?string $file = null)
    {
        $key = new Key(__METHOD__);

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

    #[RequiresPhpExtension('pdo_pgsql')]
    #[Group('integration')]
    public function testDsnWithPostgreSQL()
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $key = new Key(__METHOD__);

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

    #[RequiresPhpExtension('pdo_pgsql')]
    #[Group('integration')]
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

    #[RequiresPhpExtension('pdo_pgsql')]
    #[Group('integration')]
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

    public function testOracleClockCountsTheTimeOfDayOnce()
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('getAttribute')
            ->willReturnMap([
                [\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION],
                [\PDO::ATTR_DRIVER_NAME, 'oci'],
            ]);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains("VALUES (:id, :token, (TRUNC(systimestamp AT TIME ZONE 'UTC', 'MI') - DATE '1970-01-01') * 86400 + EXTRACT(SECOND FROM systimestamp) + 300)"))
            ->willReturn($this->createStub(\PDOStatement::class));

        $store = new PdoStore($pdo, [], 0);
        $store->save(new Key(__METHOD__));
    }
}

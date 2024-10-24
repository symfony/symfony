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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\PdoTagAwareAdapter;

/**
 * @requires extension pdo_sqlite
 *
 * @group time-sensitive
 */
abstract class AbstractPdoAdapterTest extends AdapterTestCase
{
    protected static string $dbFile;

    public static function setUpBeforeClass(): void
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');

        $pool = new PdoAdapter('sqlite:'.self::$dbFile);
        $pool->createTable();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    abstract public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface;

    public function testCreateConnectionReturnsStringWithLazyTrue()
    {
        self::assertSame('sqlite:'.self::$dbFile, AbstractAdapter::createConnection('sqlite:'.self::$dbFile));
    }

    public function testCreateConnectionReturnsPDOWithLazyFalse()
    {
        self::assertInstanceOf(\PDO::class, AbstractAdapter::createConnection('sqlite:'.self::$dbFile, ['lazy' => false]));
    }

    public function testCleanupExpiredItems()
    {
        $pdo = new \PDO('sqlite:'.self::$dbFile);

        $getCacheItemCount = fn () => (int) $pdo->query('SELECT COUNT(*) FROM cache_items')->fetch(\PDO::FETCH_COLUMN);

        $this->assertSame(0, $getCacheItemCount());

        $cache = $this->createCachePool();

        $item = $cache->getItem('some_nice_key');
        $item->expiresAfter(1);
        $item->set(1);

        $cache->save($item);
        $this->assertSame(1, $getCacheItemCount());

        sleep(2);

        $newItem = $cache->getItem($item->getKey());
        $this->assertFalse($newItem->isHit());
        $this->assertSame(0, $getCacheItemCount(), 'PDOAdapter must clean up expired items');
    }

    /**
     * @dataProvider provideDsnSQLite
     */
    public function testDsnWithSQLite(string $dsn, ?string $file = null)
    {
        try {
            $pool = new PdoAdapter($dsn);

            $item = $pool->getItem('key');
            $item->set('value');
            $this->assertTrue($pool->save($item));
        } finally {
            if (null !== $file) {
                @unlink($file);
            }
        }
    }

    public static function provideDsnSQLite()
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

        $dsn = 'pgsql:host='.$host.';user=postgres;password=password';

        try {
            $pool = new PdoAdapter($dsn);

            $item = $pool->getItem('key');
            $item->set('value');
            $this->assertTrue($pool->save($item));
        } finally {
            $pdo = new \PDO($dsn);
            $pdo->exec('DROP TABLE IF EXISTS cache_items');
        }
    }

    protected function isPruned(PdoAdapter|PdoTagAwareAdapter $cache, string $name): bool
    {
        $o = new \ReflectionObject($cache);

        $getPdoConn = $o->getMethod('getConnection');

        /** @var \PDOStatement $select */
        $select = $getPdoConn->invoke($cache)->prepare('SELECT 1 FROM cache_items WHERE item_id LIKE :id');
        $select->bindValue(':id', \sprintf('%%%s', $name));
        $select->execute();

        return 1 !== (int) $select->fetch(\PDO::FETCH_COLUMN);
    }
}

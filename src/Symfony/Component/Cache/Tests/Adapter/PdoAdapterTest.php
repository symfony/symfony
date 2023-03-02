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

use PHPUnit\Framework\SkippedTestSuiteError;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;

/**
 * @group time-sensitive
 */
class PdoAdapterTest extends AdapterTestCase
{
    protected static $dbFile;

    public static function setUpBeforeClass(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            throw new SkippedTestSuiteError('Extension pdo_sqlite required.');
        }

        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');

        $pool = new PdoAdapter('sqlite:'.self::$dbFile);
        $pool->createTable();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new PdoAdapter('sqlite:'.self::$dbFile, 'ns', $defaultLifetime);
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
     * @dataProvider provideDsn
     */
    public function testDsn(string $dsn, string $file = null)
    {
        try {
            $pool = new PdoAdapter($dsn);
            $pool->createTable();

            $item = $pool->getItem('key');
            $item->set('value');
            $this->assertTrue($pool->save($item));
        } finally {
            if (null !== $file) {
                @unlink($file);
            }
        }
    }

    public static function provideDsn()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        yield ['sqlite:'.$dbFile.'2', $dbFile.'2'];
        yield ['sqlite::memory:'];
    }

    protected function isPruned(PdoAdapter $cache, string $name): bool
    {
        $o = new \ReflectionObject($cache);

        $getPdoConn = $o->getMethod('getConnection');

        /** @var \PDOStatement $select */
        $select = $getPdoConn->invoke($cache)->prepare('SELECT 1 FROM cache_items WHERE item_id LIKE :id');
        $select->bindValue(':id', sprintf('%%%s', $name));
        $select->execute();

        return 1 !== (int) $select->fetch(\PDO::FETCH_COLUMN);
    }
}

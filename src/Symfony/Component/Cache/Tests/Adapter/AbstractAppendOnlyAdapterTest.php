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

use Cache\IntegrationTests\CachePoolTest;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

abstract class AbstractAppendOnlyAdapterTest extends CachePoolTest
{
    /**
     * @var mixed
     */
    private $cacheVersion;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    protected function setUp()
    {
        parent::setUp();
        $this->cacheVersion = $this->createRandomCachePoolVersion();
        $this->cache = $this->createVersionedCachePool($this->cacheVersion);
    }

    public function createCachePool()
    {
        $cacheVersion = $this->createRandomCachePoolVersion();

        return $this->createVersionedCachePool($cacheVersion);
    }

    /**
     * @return mixed cache version that will be used by this adapter
     */
    abstract public function createRandomCachePoolVersion();

    /**
     * @return CacheItemPoolInterface that is used in the tests that need to recreate the same cache pool
     */
    abstract public function createVersionedCachePool($cacheVersion);

    public function testBasicUsage()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $this->cache->save($item);

        $item = $this->cache->getItem('key2');
        $item->set('4712');
        $this->cache->save($item);

        $fooItem = $this->cache->getItem('key');
        $this->assertTrue($fooItem->isHit());
        $this->assertEquals('4711', $fooItem->get());

        $barItem = $this->cache->getItem('key2');
        $this->assertTrue($barItem->isHit());
        $this->assertEquals('4712', $barItem->get());

        // Removing must always return false
        $this->assertFalse($this->cache->deleteItem('key'));
        $this->assertTrue($this->cache->getItem('key')->isHit());
        $this->assertTrue($this->cache->getItem('key2')->isHit());

        // Remove everything
        $this->assertFalse($this->cache->clear());
        $this->assertTrue($this->cache->getItem('key')->isHit());
        $this->assertTrue($this->cache->getItem('key2')->isHit());
    }

    public function testClear()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $return = $this->cache->clear();
        $this->assertTrue($return, 'clear() should return true when no items are in a cache');

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        $return = $this->cache->clear();

        $this->assertFalse($return, 'clear() must return false for append-only cache when not empty.');
        $this->assertTrue($this->cache->getItem('key')->isHit(), 'Item should still be in an append-only cache, even after clear.');
    }

    public function testDeleteItem()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        $this->assertFalse($this->cache->deleteItem('key'));
        $this->assertTrue($this->cache->getItem('key')->isHit(), 'A deleted item should still be a hit in an append-only cache.');
        $this->assertTrue($this->cache->hasItem('key'), 'A deleted item should still be a hit in an append-only cache.');

        $this->assertTrue($this->cache->deleteItem('key2'), 'Deleting an item that does not exist should return true.');
    }

    public function testDeleteItems()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $items = $this->cache->getItems(['foo', 'bar', 'baz']);

        /** @var CacheItemInterface $item */
        foreach ($items as $idx => $item) {
            $item->set($idx);
            $this->cache->save($item);
        }

        // All should be a hit but 'biz'
        $this->assertTrue($this->cache->getItem('foo')->isHit());
        $this->assertTrue($this->cache->getItem('bar')->isHit());
        $this->assertTrue($this->cache->getItem('baz')->isHit());
        $this->assertFalse($this->cache->getItem('biz')->isHit());

        $return = $this->cache->deleteItems(['foo', 'bar', 'biz']);
        $this->assertFalse($return, 'Deleting should return false in append-only cache');

        $this->assertTrue($this->cache->getItem('foo')->isHit(), 'Deleting shouldn\'t work for append-only cache');
        $this->assertTrue($this->cache->getItem('bar')->isHit(), 'Deleting shouldn\'t work for append-only cache');
        $this->assertTrue($this->cache->getItem('baz')->isHit(), 'Deleting shouldn\'t work for append-only cache');
        $this->assertFalse($this->cache->getItem('biz')->isHit());
    }

    public function testSaveExpired()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAt(\DateTime::createFromFormat('U', time() - 1));
        $this->cache->save($item);
        $item = $this->cache->getItem('key');
        $this->assertFalse($item->isHit(), 'Cache should not return expired items');
    }

    public function testSaveWithoutExpire()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $item = $this->cache->getItem('test_ttl_null');
        $item->set('data');
        $this->cache->save($item);

        // Use a new pool instance to ensure that we don't it any caches
        $pool = $this->createVersionedCachePool($this->cacheVersion);
        $item = $pool->getItem('test_ttl_null');

        $this->assertTrue($item->isHit(), 'Cache should have retrieved the items');
        $this->assertEquals('data', $item->get());
    }

    public function testDeleteDeferredItem()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $this->cache->saveDeferred($item);

        $this->cache->deleteItem('key');
        $this->assertFalse($this->cache->hasItem('key'), 'You must be able to delete a deferred item before committed. ');
        $this->assertFalse($this->cache->getItem('key')->isHit(), 'You must be able to delete a deferred item before committed. ');

        $this->cache->commit();
        $this->assertFalse($this->cache->hasItem('key'), 'A deleted item should not reappear after commit. ');
        $this->assertFalse($this->cache->getItem('key')->isHit(), 'A deleted item should not reappear after commit. ');
    }

    public function testDeferredSaveWithoutCommit()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $this->prepareDeferredSaveWithoutCommit();
        gc_collect_cycles();

        $cache = $this->createVersionedCachePool($this->cacheVersion);
        $this->assertTrue($cache->getItem('key')->isHit(), 'A deferred item should automatically be committed on CachePool::__destruct().');
    }

    public function testSaveDeferredOverwrite()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);
        $item->set('new value');
        $this->cache->saveDeferred($item);

        $this->cache->commit();
        $item = $this->cache->getItem('key');
        $this->assertEquals('new value', $item->get());
    }

    private function prepareDeferredSaveWithoutCommit()
    {
        $cache = $this->cache;
        $this->cache = null;

        $item = $cache->getItem('key');
        $item->set('4711');
        $cache->saveDeferred($item);
    }
}

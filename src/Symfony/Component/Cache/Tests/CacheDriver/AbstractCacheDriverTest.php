<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\CacheDriver;

use Symfony\Component\Cache\CacheDriver;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Cache\CacheProfiler;
use Symfony\Component\Cache\Item\CacheItem;
use Symfony\Component\HttpKernel\Tests\Logger;

abstract class AbstractCacheDriverTest extends \PHPUnit_Framework_TestCase
{
    private $cacheItemNamespace = '\\Symfony\\Component\\Cache\\Item\\CacheItem';

    /**
     * @var CacheDriver
     */
    protected $cache;

    protected function setUp()
    {
        $this->cache = new CacheDriver($this->_getTestDriver(), 'test', 'test');

        $this->cache->setLogger(new Logger('demo'));

        $this->cache->setProfiler(new CacheProfiler(new Stopwatch()));
    }

    protected function tearDown()
    {
        $this->cache = null;
    }

    abstract protected function _getTestDriver();

    /**
     * @covers Symfony\Component\Cache\CacheDriver::setDefaultTtl
     * @covers Symfony\Component\Cache\CacheDriver::getDefaultTtl
     */
    public function testTtl()
    {
        $this->assertInstanceOf('\\Symfony\\Component\\Cache\\CacheDriver', $this->cache->setDefaultTtl(4));

        $this->assertEquals(4, $this->cache->getDefaultTtl());
    }

    /**
     * @covers Symfony\Component\Cache\CacheDriver::set
     */
    public function testSet()
    {
        $cacheKeys = $this->generateCacheKeys();

        foreach ($cacheKeys as $cacheKey) {
            $this->assertTrue($this->cache->set($cacheKey));
        }
    }

    /**
     * @covers Symfony\Component\Cache\CacheDriver::get
     * @depends testSet
     */
    public function testGet()
    {
        $cacheItem = new CacheItem('demo', 'demo', 100);

        $exists = null;
        $result = $this->cache->get($cacheItem, $exists);
        $this->assertFalse($exists, "The value of exists should be false when the key does not exist");
        $this->assertInstanceOf($this->cacheItemNamespace, $result, "A CacheItem should be returned even if the item is not in cache");

        $exists = null;
        $result = $this->cache->get('demo', $exists);
        $this->assertFalse($exists, "The value of exists should be false when the key does not exist");
        $this->assertInstanceOf($this->cacheItemNamespace, $result, "A CacheItem should be returned even if the item is not in cache");

        $cacheKeys = $this->generateCacheKeys();
        $this->populateCache($cacheKeys);

        foreach ($cacheKeys as $cacheKey) {
            $exists = null;
            $result = $this->cache->get($cacheKey, $exists);
            $this->assertTrue($exists, sprintf("The cache key should be present for %s", $cacheKey->getKey()));
            $this->assertInstanceOf($this->cacheItemNamespace, $result, sprintf("The value retrieved from cache for key %s should be a instance of CacheItem", $cacheKey->getKey()));
        }
    }

    /**
     * @covers Symfony\Component\Cache\CacheDriver::exists
     * @depends testSet
     */
    public function testExists()
    {
        $cacheItem = new CacheItem('demo', 'demo', 100);
        $this->assertFalse($this->cache->exists($cacheItem), "The value of exists should be false when the key does not exist");

        $this->assertFalse($this->cache->exists('demo'), "The value of exists should be false when the key does not exist");

        $cacheKeys = $this->generateCacheKeys();
        $this->populateCache($cacheKeys);

        foreach ($cacheKeys as $cacheKey) {
            $this->assertTrue($this->cache->exists($cacheKey), sprintf("The cache key %s should be present", $cacheKey->getKey()));
        }
    }

    /**
     * @covers Symfony\Component\Cache\CacheDriver::remove
     * @depends testSet
     */
    public function testRemove()
    {
        $cacheItem = new CacheItem('demo', 'demo', 100);
        $this->assertFalse($this->cache->remove($cacheItem), "One should not be able to remove an inexistent key");

        $this->assertFalse($this->cache->remove('demo'), "One should not be able to remove an inexistent key");

        $cacheKeys = $this->generateCacheKeys();
        $this->populateCache($cacheKeys);
        foreach ($cacheKeys as $cacheKey) {
            $this->assertTrue($this->cache->remove($cacheKey), sprintf("The cache key %s should be deleted", $cacheKey->getKey()));
        }
    }

    /**
     * @covers Symfony\Component\Cache\CacheDriver::setMultiple
     * @depends testSet
     */
    public function testSetMultiple()
    {
        $cacheKeys = $this->generateCacheKeys();

        $this->cache->setMultiple($cacheKeys);
    }

    /**
     * @covers Symfony\Component\Cache\CacheDriver::getMultiple
     * @depends testSet
     */
    public function testGetMultiple()
    {
        $cacheKeys = $this->generateCacheKeys();

        $this->populateCache($cacheKeys);
        $results = $this->cache->getMultiple($cacheKeys);

        foreach ($results as $result) {
            $this->assertInstanceOf($this->cacheItemNamespace, $result, 'The retrieved value should always be a CacheItem');
        }
    }

    /**
     * @covers Symfony\Component\Cache\CacheDriver::removeMultiple
     * @depends testSet
     */
    public function testRemoveMultiple()
    {
        $cacheKeys = $this->generateCacheKeys();

        $this->populateCache($cacheKeys);
        $results = $this->cache->removeMultiple($cacheKeys);

        $this->assertEquals(count($cacheKeys), array_sum($results));
    }

    /**
     * @covers Symfony\Component\Cache\CacheDriver::existsMultiple
     * @depends testSet
     */
    public function testExistsMultiple()
    {
        $cacheKeys = $this->generateCacheKeys();

        $this->populateCache($cacheKeys);
        $results = $this->cache->existsMultiple($cacheKeys);

        foreach ($results as $result) {
            $this->assertTrue($result, "One should be able to delete a cache item that exists");
        }
    }

    /**
     * @return CacheItem[]
     */
    public function generateCacheKeys()
    {
        return array(
            'stringItem' => new CacheItem('demo_string', 'demo value', 100),
            'boolItem' => new CacheItem('demo_bool', false, 100),
            'intItem' => new CacheItem('demo_int', 0, 100),
            'objItem' => new CacheItem('demo_obj', new \stdClass(), 100),
            'nullItem' => new CacheItem('demo_null', null, 100),
        );
    }

    /**
     * Write the specified items to cache
     *
     * @param CacheItem[] $items
     */
    public function populateCache(array $items)
    {
        foreach ($items as $item) {
            $this->cache->set($item);
        }
    }
}

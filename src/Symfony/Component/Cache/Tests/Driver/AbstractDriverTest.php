<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Driver;

use Symfony\Component\Cache\Driver\DriverInterface;
use Symfony\Component\Cache\Driver\BatchDriverInterface;

abstract class AbstractDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DriverInterface|BatchDriverInterface
     */
    protected $driver;

    protected function setUp()
    {
        $this->driver = $this->_getTestDriver();
    }

    protected function tearDown()
    {
        $this->driver = null;
    }

    /**
     * @return DriverInterface|BatchDriverInterface
     */
    abstract protected function _getTestDriver();

    public function testSet()
    {
        if (!$this->driver instanceof DriverInterface) {
            $this->markTestSkipped('Driver does not support single operations');
        }

        // More simple keys
        $items = $this->generateCacheKeys();

        foreach ($items as $item) {
            $this->assertTrue($this->driver->set($item['key'], $item['value'], $item['ttl']));
        }
    }

    public function testSetObject()
    {
        if (!$this->driver instanceof DriverInterface) {
            $this->markTestSkipped('Driver does not support single operations');
        }

        if (!$this->driver->hasSerializationSupport()) {
            $this->markTestSkipped('Driver does not support serialization');
        }

        $items = $this->generateObjectCachekeys();
        foreach ($items as $item) {
            $this->assertTrue($this->driver->set($item['key'], $item['value'], $item['ttl']));
        }
    }

    /**
     * @depends testSet
     */
    public function testGet()
    {
        if (!$this->driver instanceof DriverInterface) {
            $this->markTestSkipped('Driver does not support single operations');
        }

        $cacheItems = $this->generateCacheKeys();
        $this->populateCache($cacheItems);

        foreach ($cacheItems as $cacheItem) {
            $this->assertEquals($cacheItem['value'], $this->driver->get($cacheItem['key']));
        }
    }

    public function testGetObject()
    {
        if (!$this->driver instanceof DriverInterface) {
            $this->markTestSkipped('Driver does not support single operations');
        }

        if (!$this->driver->hasSerializationSupport()) {
            $this->markTestSkipped('Driver does not support serialization');
        }

        $cacheItems = $this->generateCacheKeys();
        $this->populateCache($cacheItems);

        foreach ($cacheItems as $cacheItem) {
            $this->assertEquals($cacheItem['value'], $this->driver->get($cacheItem['key']));
        }

        $exists = null;
        $this->driver->get('random_cache_key' . rand(1, 100), $exists);
        $this->assertFalse($exists);
    }

    /**
     * @depends testSet
     */
    public function testExists()
    {
        if (!$this->driver instanceof DriverInterface) {
            $this->markTestSkipped('Driver does not support single operations');
        }

        $cacheItems = $this->generateCacheKeys();
        $this->populateCache($cacheItems);

        foreach ($cacheItems as $cacheItem) {
            $this->assertTrue($this->driver->exists($cacheItem['key']));
        }

        $this->assertFalse($this->driver->get('random_cache_key' . rand(1, 100)));
    }

    /**
     * @depends testSetObject
     */
    public function testExistsObject()
    {
        if (!$this->driver instanceof DriverInterface) {
            $this->markTestSkipped('Driver does not support single operations');
        }

        if (!$this->driver->hasSerializationSupport()) {
            $this->markTestSkipped('Driver does not support serialization');
        }

        $cacheItems = $this->generateObjectCachekeys();
        $this->populateCache($cacheItems);

        foreach ($cacheItems as $cacheItem) {
            $this->assertTrue($this->driver->exists($cacheItem['key']));
        }

        $this->assertFalse($this->driver->exists('random_cache_key' . rand(1, 100)));
    }

    /**
     * @depends testSet
     */
    public function testRemove()
    {
        if (!$this->driver instanceof DriverInterface) {
            $this->markTestSkipped('Driver does not support single operations');
        }

        $cacheItems = $this->generateCacheKeys();
        $this->populateCache($cacheItems);

        foreach ($cacheItems as $cacheItem) {
            $this->assertTrue($this->driver->remove($cacheItem['key']));
        }

        $this->assertFalse($this->driver->remove('random_cache_key' . rand(1, 100)));
    }

    /**
     * @depends testSetObject
     */
    public function testRemoveObject()
    {
        if (!$this->driver instanceof DriverInterface) {
            $this->markTestSkipped('Driver does not support single operations');
        }

        if (!$this->driver->hasSerializationSupport()) {
            $this->markTestSkipped('Driver does not support serialization');
        }

        $cacheItems = $this->generateObjectCachekeys();
        $this->populateCache($cacheItems);

        foreach ($cacheItems as $cacheItem) {
            $this->assertTrue($this->driver->remove($cacheItem['key']));
        }

        $this->assertFalse($this->driver->remove('random_cache_key' . rand(1, 100)));
    }

    public function testSetMultiple()
    {
        if (!$this->driver instanceof BatchDriverInterface) {
            $this->markTestSkipped('Driver does not support multiple operations');
        }

        $cacheItems = $this->generateCacheKeys();
        $results = $this->driver->setMultiple($cacheItems, 10);

        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    public function testSetMultipleObject()
    {
        if (!$this->driver instanceof BatchDriverInterface) {
            $this->markTestSkipped('Driver does not support multiple operations');
        }

        if (!$this->driver->hasSerializationSupport()) {
            $this->markTestSkipped('Driver does not support serialization');
        }

        $cacheItems = $this->generateObjectCacheKeys();
        $results = $this->driver->setMultiple($cacheItems, 10);

        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    public function testGetMultiple()
    {
        if (!$this->driver instanceof BatchDriverInterface) {
            $this->markTestSkipped('Driver does not support multiple operations');
        }

        $cacheItems = $this->generateCacheKeys();
        $this->driver->setMultiple($cacheItems, 10);

        $results = $this->driver->getMultiple(array_keys($cacheItems));

        $this->assertEquals(array_keys($cacheItems), array_keys($results), 'The driver should always return the same set of keys sent');

        foreach ($results as $key => $result) {
            $this->assertEquals($cacheItems[$key]['key'], $result['key']);
            $this->assertEquals($cacheItems[$key]['value'], $result['value']);
            $this->assertEquals($cacheItems[$key]['ttl'], $result['ttl']);
        }
    }

    public function testGetMultipleObject()
    {
        if (!$this->driver instanceof BatchDriverInterface) {
            $this->markTestSkipped('Driver does not support multiple operations');
        }

        if (!$this->driver->hasSerializationSupport()) {
            $this->markTestSkipped('Driver does not support serialization');
        }

        $cacheItems = $this->generateObjectCacheKeys();
        $this->driver->setMultiple($cacheItems, 10);

        $results = $this->driver->getMultiple(array_keys($cacheItems));

        $this->assertEquals(array_keys($cacheItems), array_keys($results), 'The driver should always return the same set of keys sent');

        foreach ($results as $key => $result) {
            $this->assertEquals($cacheItems[$key]['key'], $result['key']);
            $this->assertEquals($cacheItems[$key]['value'], $result['value']);
            $this->assertEquals($cacheItems[$key]['ttl'], $result['ttl']);
        }
    }

    public function testRemoveMultiple()
    {
        if (!$this->driver instanceof BatchDriverInterface) {
            $this->markTestSkipped('Driver does not support multiple operations');
        }

        $cacheItems = $this->generateCacheKeys();
        $this->driver->setMultiple($cacheItems, 10);

        $results = $this->driver->removeMultiple(array_keys($cacheItems));

        $this->assertEquals(array_keys($cacheItems), array_keys($results), 'The driver should always return the same set of keys sent');

        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    public function testRemoveMultipleObject()
    {
        if (!$this->driver instanceof BatchDriverInterface) {
            $this->markTestSkipped('Driver does not support multiple operations');
        }

        if (!$this->driver->hasSerializationSupport()) {
            $this->markTestSkipped('Driver does not support serialization');
        }

        $cacheItems = $this->generateObjectCachekeys();
        $this->driver->setMultiple($cacheItems, 10);

        $results = $this->driver->removeMultiple(array_keys($cacheItems));

        $this->assertEquals(array_keys($cacheItems), array_keys($results), 'The driver should always return the same set of keys sent');

        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    public function testExistsMultiple()
    {
        if (!$this->driver instanceof BatchDriverInterface) {
            $this->markTestSkipped('Driver does not support multiple operations');
        }

        $cacheItems = $this->generateCacheKeys();
        $this->driver->setMultiple($cacheItems, 10);

        $results = $this->driver->existsMultiple(array_keys($cacheItems));

        $this->assertEquals(array_keys($cacheItems), array_keys($results), 'The driver should always return the same set of keys sent');

        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    public function testExistsMultipleObject()
    {
        if (!$this->driver instanceof BatchDriverInterface) {
            $this->markTestSkipped('Driver does not support multiple operations');
        }

        if (!$this->driver->hasSerializationSupport()) {
            $this->markTestSkipped('Driver does not support serialization');
        }

        $cacheItems = $this->generateObjectCacheKeys();
        $this->driver->setMultiple($cacheItems, 10);

        $results = $this->driver->existsMultiple(array_keys($cacheItems));

        $this->assertEquals(array_keys($cacheItems), array_keys($results), 'The driver should always return the same set of keys sent');

        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    /**
     * @return array
     */
    public function generateCacheKeys()
    {
        return array(
            'stringItem' => array('key' => 'demo_string', 'value' => 'demo value', 'ttl' => 100),
            'boolFItem' => array('key' => 'demo_boolf', 'value' => false, 'ttl' => 100),
            'boolTItem' => array('key' => 'demo_boolt', 'value' => true, 'ttl' => 100),
            'int0Item' => array('key' => 'demo_int0', 'value' => 0, 'ttl' => 100),
            'int1Item' => array('key' => 'demo_int1', 'value' => 1, 'ttl' => 100),
        );
    }

    public function generateObjectCachekeys()
    {
        return array(
            'nullItem' => array('key' => 'demo_null', 'value' => null, 'ttl' => 100),
            'objItem' => array('key' => 'demo_obj', 'value' => new \stdClass(), 'ttl' => 100),
        );
    }

    /**
     * Write the specified items to cache
     *
     * @param array $items
     */
    public function populateCache(array $items)
    {
        foreach ($items as $item) {
            $this->driver->set($item['key'], $item['value'], $item['ttl']);
        }
    }

}
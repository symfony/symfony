<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use Symfony\Component\Cache\Item\CacheItem;

class CacheItemTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CacheItem
     */
    protected $cacheItem;

    private $cacheItemNamespace = '\\Symfony\\Component\\Cache\\Item\\CacheItem';

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->cacheItem = new CacheItem('test', 'test value', 10);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        $this->cacheItem = null;
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::setKey
     */
    public function testSetKey()
    {
        $result = $this->cacheItem->setKey('demo');

        $this->assertInstanceOf($this->cacheItemNamespace, $result, 'Setting the cache key should return the CacheItem object');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::getKey
     * @depends testSetKey
     */
    public function testGetKey()
    {
        $key = $this->cacheItem->getKey();
        $this->assertEquals('test', $key, 'The default key should be present after constructor');

        $key = $this->cacheItem->setKey('demo')->getKey();
        $this->assertEquals('demo', $key, 'A new key should be possible to be assigned to a existing CacheItem');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::setValue
     */
    public function testSetValue()
    {
        $result = $this->cacheItem->setValue('demo');

        $this->assertInstanceOf($this->cacheItemNamespace, $result, 'Setting the cache value should return the CacheItem object');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::getValue
     * @depends testSetValue
     */
    public function testGetValue()
    {
        $value = $this->cacheItem->getValue();
        $this->assertEquals('test value', $value, 'The default value should be present after constructor');

        $value = $this->cacheItem->setvalue('demo')->getValue();
        $this->assertEquals('demo', $value, 'A new value should be possible to be assigned to a existing CacheItem');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::setTtl
     */
    public function testSetTtl()
    {
        $result = $this->cacheItem->setTtl(100);

        $this->assertInstanceOf($this->cacheItemNamespace, $result, 'Setting the cache TTL should return the CacheItem object');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::getTtl
     */
    public function testGetTtl()
    {
        $value = $this->cacheItem->getTtl();
        $this->assertEquals(10, $value, 'The default ttl should be present after constructor');

        $value = $this->cacheItem->setTtl(15)->getTtl();
        $this->assertEquals(15, $value, 'A new ttl should be possible to be assigned to a existing CacheItem');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::getRemainingTtl
     */
    public function testGetRemainingTtl()
    {
        $remainingTtl = $this->cacheItem->getRemainingTtl();

        $this->assertEquals($this->cacheItem->getTtl(), $remainingTtl, 'The default value of remaining ttl on a unset object should be the same as ttl');

        // Emulate the save process
        $cacheItem = serialize($this->cacheItem);
        sleep(1);
        $cacheItem = unserialize($cacheItem);

        $this->assertEquals($remainingTtl - 1, $cacheItem->getRemainingTtl(), 'The remaining ttl should be TTL - 1 since we\'ve slept for 1 sec only');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::getNamespace
     */
    public function testGetNamespace()
    {
        $value = $this->cacheItem->getNamespace();
        $this->assertEquals('', $value, 'The default namespace should be empty after constructor');

        $value = $this->cacheItem->setNamespace('demo\demo')->getNamespace();
        $this->assertEquals('demo\demo', $value, 'A new namespace should be possible to be assigned to a existing CacheItem');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::setNamespace
     */
    public function testSetNamespace()
    {
        $result = $this->cacheItem->setNamespace('demo\demo');

        $this->assertInstanceOf($this->cacheItemNamespace, $result, 'Setting the cache namespace should return the CacheItem object');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::getTags
     */
    public function testGetTags()
    {
        $value = $this->cacheItem->getTags();
        $this->assertEquals(array(), $value, 'The default tags should be empty after constructor');

        $value = $this->cacheItem->setTags(array('demo', 'demoTag'))->getTags();
        $this->assertEquals(array('demo', 'demoTag'), $value, 'New tags should be possible to be assigned to a existing CacheItem');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::setTags
     */
    public function testSetTags()
    {
        $result = $this->cacheItem->setTags(array('demo', 'demotag'));

        $this->assertInstanceOf($this->cacheItemNamespace, $result, 'Setting the cache tags should return the CacheItem object');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::setMetadata
     */
    public function testSetMetadata()
    {
        $result = $this->cacheItem->setMetadata('meta', 'demo');

        $this->assertInstanceOf($this->cacheItemNamespace, $result, 'Setting some cache metadata should return the CacheItem object');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::hasMetadata
     */
    public function testHasMetadata()
    {
        $value = $this->cacheItem->hasMetadata();
        $this->assertFalse($value, 'There should not be metadata for the object after constructor');

        $value = $this->cacheItem->setMetadata('demo', 'demoTag')->hasMetadata('demo');
        $this->assertTrue($value, 'New metadata item should be possible to be assigned to a existing CacheItem');

        $value = $this->cacheItem->hasMetadata('demo2');
        $this->assertFalse($value, 'Inexistent metadata item should return empty string always');
    }

    /**
     * @covers Symfony\Component\Cache\Item\CacheItem::getMetadata
     */
    public function testGetMetadata()
    {
        $value = $this->cacheItem->hasMetadata();
        $this->assertFalse($value, 'There should not be metadata for the object after constructor');

        $value = $this->cacheItem->setMetadata('demo', 'demoTag')->getMetadata('demo');
        $this->assertEquals('demoTag', $value, 'New metadata item should be possible to be assigned to a existing CacheItem');

        $value = $this->cacheItem->setMetadata('demoMeta', 'demoTags')->getMetadata();
        $this->assertEquals(array('demo' => 'demoTag', 'demoMeta' => 'demoTags'), $value, 'Getting metadata without a specific key should return all metadata');

        $value = $this->cacheItem->getMetadata('demo2');
        $this->assertEquals('', $value, 'Inexistent metadata item should return empty string always');
    }
}

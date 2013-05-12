<?php

namespace Symfony\Component\Cache\Tests\Acceptance;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\FreshItem;
use Symfony\Component\Cache\Data\NullResult;

class CoreTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider Symfony\Component\Cache\Tests\Acceptance\DataProvider::provideCaches */
    public function testWhenIStoreAnItemIGetInfoAboutIt(Cache $cache)
    {
        $item = new FreshItem('key', 'value');

        $storedItem = $cache->set($item);

        $this->assertTrue($storedItem instanceof CachedItem);
        $this->assertEquals('value', $storedItem->getValue());
        $this->assertEquals('key', $storedItem->getKey());
        $this->assertTrue($storedItem->isHit());
    }

    /** @dataProvider Symfony\Component\Cache\Tests\Acceptance\DataProvider::provideCaches */
    public function testWhenAnItemIsStoredICanFetchIt(Cache $cache)
    {
        $item = new FreshItem('key', 'value');

        $cache->set($item);
        $fetchedItem = $cache->get('key');

        $this->assertTrue($fetchedItem instanceof CachedItem);
        $this->assertEquals('value', $fetchedItem->getValue());
        $this->assertEquals('key', $fetchedItem->getKey());
        $this->assertTrue($fetchedItem->isHit());
    }

    /** @dataProvider Symfony\Component\Cache\Tests\Acceptance\DataProvider::provideCaches */
    public function testWhenAnItemIsStoredAndDeletedICantFetchIt(Cache $cache)
    {
        $item = new FreshItem('key', 'data');

        $cache->set($item);
        $cache->remove('key');
        $fetchedItem = $cache->get('key');

        $this->assertTrue($fetchedItem instanceof NullResult);
        $this->assertFalse($fetchedItem->isHit());
    }
}

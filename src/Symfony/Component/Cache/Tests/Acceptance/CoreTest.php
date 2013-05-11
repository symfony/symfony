<?php

namespace Symfony\Component\Cache\Tests\Acceptance;

use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\FreshItem;
use Symfony\Component\Cache\Data\NullResult;

class CoreTest extends AcceptanceTest
{
    public function testWhenIStoreAnItemIGetInfoAboutIt()
    {
        $cache = $this->createCache();
        $item = new FreshItem('key', 'value');

        $storedItem = $cache->set($item);

        $this->assertTrue($storedItem instanceof CachedItem);
        $this->assertEquals('value', $storedItem->getValue());
        $this->assertEquals('key', $storedItem->getKey());
        $this->assertTrue($storedItem->isHit());
    }

    public function testWhenAnItemIsStoredICanFetchIt()
    {
        $cache = $this->createCache();
        $item = new FreshItem('key', 'value');

        $cache->set($item);
        $fetchedItem = $cache->get('key');

        $this->assertTrue($fetchedItem instanceof CachedItem);
        $this->assertEquals('value', $fetchedItem->getValue());
        $this->assertEquals('key', $fetchedItem->getKey());
        $this->assertTrue($fetchedItem->isHit());
    }

    public function testWhenAnItemIsStoredAndDeletedICantFetchIt()
    {
        $item = new FreshItem('key', 'data');
        $cache = $this->createCache();

        $cache->set($item);
        $cache->remove('key');
        $fetchedItem = $cache->get('key');

        $this->assertTrue($fetchedItem instanceof NullResult);
        $this->assertFalse($fetchedItem->isHit());
    }
}

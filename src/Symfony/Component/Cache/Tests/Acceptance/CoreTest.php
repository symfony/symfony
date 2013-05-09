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
        $item = new FreshItem('key', 'data');

        $storedItem = $cache->store($item);

        $this->assertTrue($storedItem instanceof CachedItem);
        $this->assertEquals('data', $storedItem->getData());
        $this->assertEquals('key', $storedItem->getKey());
        $this->assertTrue($storedItem->isValid());
        $this->assertTrue($storedItem->isCached());
        $this->assertFalse($storedItem->isCollection());
    }

    public function testWhenAnItemIsStoredICanFetchIt()
    {
        $cache = $this->createCache();
        $item = new FreshItem('key', 'data');

        $cache->store($item);
        $fetchedItem = $cache->fetch('key');

        $this->assertTrue($fetchedItem instanceof CachedItem);
        $this->assertEquals('data', $fetchedItem->getData());
        $this->assertEquals('key', $fetchedItem->getKey());
        $this->assertTrue($fetchedItem->isValid());
        $this->assertTrue($fetchedItem->isCached());
        $this->assertFalse($fetchedItem->isCollection());
    }

    public function testWhenAnItemIsStoredAndDeletedICantFetchIt()
    {
        $item = new FreshItem('key', 'data');
        $cache = $this->createCache();

        $cache->store($item);
        $cache->delete('key');
        $fetchedItem = $cache->fetch('key');

        $this->assertTrue($fetchedItem instanceof NullResult);
        $this->assertFalse($fetchedItem->isValid());
        $this->assertFalse($fetchedItem->isCached());
        $this->assertFalse($fetchedItem->isCollection());
    }
}

<?php

namespace Symfony\Component\Cache\Tests\Acceptance;

use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\FreshItem;

class TagTest extends AcceptanceTest
{
    public function testWhenIStoreAnItemWithTagsIFetchThem()
    {
        $cache = $this->createCache();
        $item = new FreshItem('key', 'data');
        $item->metadata->set('tags', array('tag1', 'tag2'));

        $cache->store($item);
        $fetchedItem = $cache->fetch('key');

        $this->assertEquals(array('tag1', 'tag2'),  $fetchedItem->metadata->get('tags'));
    }

    public function testWhenIStoreItemsWithTagICanFetchThemByTag()
    {
        $cache = $this->createCache();

        $item = new FreshItem('key1', 'data1');
        $item->metadata->set('tags', array('tag1', 'tag2'));
        $cache->store($item);

        $item = new FreshItem('key2', 'data2');
        $item->metadata->set('tags', array('tag1', 'tag3'));
        $cache->store($item);

        $item = new FreshItem('key3', 'data3');
        $item->metadata->set('tags', array('tag2', 'tag3'));
        $cache->store($item);

        $fetchedCollection = $cache->fetch(array('tag' => 'tag1'));

        $this->assertTrue($fetchedCollection instanceof Collection);
        $this->assertEquals('data1', $fetchedCollection->get('key1')->getData());
        $this->assertEquals('data2', $fetchedCollection->get('key2')->getData());
        $this->assertEquals(2, count($fetchedCollection->all()));

        $fetchedCollection = $cache->fetch(array('tag' => 'tag2'));

        $this->assertTrue($fetchedCollection instanceof Collection);
        $this->assertEquals('data1', $fetchedCollection->get('key1')->getData());
        $this->assertEquals('data3', $fetchedCollection->get('key3')->getData());
        $this->assertEquals(2, count($fetchedCollection->all()));

        $fetchedCollection = $cache->fetch(array('tag' => 'tag3'));

        $this->assertTrue($fetchedCollection instanceof Collection);
        $this->assertEquals('data2', $fetchedCollection->get('key2')->getData());
        $this->assertEquals('data3', $fetchedCollection->get('key3')->getData());
        $this->assertEquals(2, count($fetchedCollection->all()));
    }
}

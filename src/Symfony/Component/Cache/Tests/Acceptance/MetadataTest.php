<?php

namespace Symfony\Component\Cache\Tests\Acceptance;

use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\FreshItem;
use Symfony\Component\Cache\Data\Metadata;

class MetadataTest extends AcceptanceTest
{
    public function testWhenIStoreAnItemWithMetadataIFetchThem()
    {
        $cache = $this->createCache();
        $item = new FreshItem('key', 'data');
        $item->metadata->set('metakey1', 'metadata1');
        $item->metadata->set('metakey2', 'metadata2');

        $cache->store($item);
        $fetchedItem = $cache->fetch('key');

        $this->assertTrue($fetchedItem instanceof CachedItem);
        $this->assertTrue($fetchedItem->metadata instanceof Metadata);
        $this->assertEquals('metadata1',  $fetchedItem->metadata->get('metakey1'));
        $this->assertEquals('metadata2',  $fetchedItem->metadata->get('metakey2'));
    }
}

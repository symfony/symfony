<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Acceptance;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\FreshItem;

class TagTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider Symfony\Component\Cache\Tests\Acceptance\DataProvider::provideCaches */
    public function testWhenIStoreAnItemWithTagsIFetchThem(Cache $cache)
    {
        $item = new FreshItem('key', 'data');
        $item->metadata->set('tags', array('tag1', 'tag2'));

        $cache->set($item);
        $fetchedItem = $cache->get('key');

        $this->assertEquals(array('tag1', 'tag2'),  $fetchedItem->metadata->get('tags'));
    }

    /** @dataProvider Symfony\Component\Cache\Tests\Acceptance\DataProvider::provideCaches */
    public function testWhenIStoreItemsWithTagICanFetchThemByTag(Cache $cache)
    {
        $item = new FreshItem('key1', 'data1');
        $item->metadata->set('tags', array('tag1', 'tag2'));
        $cache->set($item);

        $item = new FreshItem('key2', 'data2');
        $item->metadata->set('tags', array('tag1', 'tag3'));
        $cache->set($item);

        $item = new FreshItem('key3', 'data3');
        $item->metadata->set('tags', array('tag2', 'tag3'));
        $cache->set($item);

        $fetchedCollection = $cache->get(array('tag' => 'tag1'));

        $this->assertTrue($fetchedCollection instanceof Collection);
        $this->assertEquals('data1', $fetchedCollection->get('key1')->getValue());
        $this->assertEquals('data2', $fetchedCollection->get('key2')->getValue());
        $this->assertEquals(2, count($fetchedCollection->all()));

        $fetchedCollection = $cache->get(array('tag' => 'tag2'));

        $this->assertTrue($fetchedCollection instanceof Collection);
        $this->assertEquals('data1', $fetchedCollection->get('key1')->getValue());
        $this->assertEquals('data3', $fetchedCollection->get('key3')->getValue());
        $this->assertEquals(2, count($fetchedCollection->all()));

        $fetchedCollection = $cache->get(array('tag' => 'tag3'));

        $this->assertTrue($fetchedCollection instanceof Collection);
        $this->assertEquals('data2', $fetchedCollection->get('key2')->getValue());
        $this->assertEquals('data3', $fetchedCollection->get('key3')->getValue());
        $this->assertEquals(2, count($fetchedCollection->all()));
    }
}

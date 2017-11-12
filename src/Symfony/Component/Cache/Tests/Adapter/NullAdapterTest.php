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

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;

/**
 * @group time-sensitive
 */
class NullAdapterTest extends TestCase
{
    public function createCachePool()
    {
        return new NullAdapter();
    }

    public function testGetItem(): void
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit is false.");
    }

    public function testHasItem(): void
    {
        $this->assertFalse($this->createCachePool()->hasItem('key'));
    }

    public function testGetItems(): void
    {
        $adapter = $this->createCachePool();

        $keys = array('foo', 'bar', 'baz', 'biz');

        /** @var CacheItemInterface[] $items */
        $items = $adapter->getItems($keys);
        $count = 0;

        foreach ($items as $key => $item) {
            $itemKey = $item->getKey();

            $this->assertEquals($itemKey, $key, 'Keys must be preserved when fetching multiple items');
            $this->assertTrue(in_array($key, $keys), 'Cache key can not change.');
            $this->assertFalse($item->isHit());

            // Remove $key for $keys
            foreach ($keys as $k => $v) {
                if ($v === $key) {
                    unset($keys[$k]);
                }
            }

            ++$count;
        }

        $this->assertSame(4, $count);
    }

    public function testIsHit(): void
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        $this->assertFalse($item->isHit());
    }

    public function testClear(): void
    {
        $this->assertTrue($this->createCachePool()->clear());
    }

    public function testDeleteItem(): void
    {
        $this->assertTrue($this->createCachePool()->deleteItem('key'));
    }

    public function testDeleteItems(): void
    {
        $this->assertTrue($this->createCachePool()->deleteItems(array('key', 'foo', 'bar')));
    }

    public function testSave(): void
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit is false.");

        $this->assertFalse($adapter->save($item));
    }

    public function testDeferredSave(): void
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit is false.");

        $this->assertFalse($adapter->saveDeferred($item));
    }

    public function testCommit(): void
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit is false.");

        $this->assertFalse($adapter->saveDeferred($item));
        $this->assertFalse($this->createCachePool()->commit());
    }
}

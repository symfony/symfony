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

    public function testGetItem()
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        self::assertFalse($item->isHit());
        self::assertNull($item->get(), "Item's value must be null when isHit is false.");
    }

    public function testGet()
    {
        $adapter = $this->createCachePool();

        $fetched = [];
        $adapter->get('myKey', function ($item) use (&$fetched) { $fetched[] = $item; });
        self::assertCount(1, $fetched);
        $item = $fetched[0];
        self::assertFalse($item->isHit());
        self::assertNull($item->get(), "Item's value must be null when isHit is false.");
        self::assertSame('myKey', $item->getKey());
    }

    public function testHasItem()
    {
        self::assertFalse($this->createCachePool()->hasItem('key'));
    }

    public function testGetItems()
    {
        $adapter = $this->createCachePool();

        $keys = ['foo', 'bar', 'baz', 'biz'];

        /** @var CacheItemInterface[] $items */
        $items = $adapter->getItems($keys);
        $count = 0;

        foreach ($items as $key => $item) {
            $itemKey = $item->getKey();

            self::assertEquals($itemKey, $key, 'Keys must be preserved when fetching multiple items');
            self::assertContains($key, $keys, 'Cache key cannot change.');
            self::assertFalse($item->isHit());

            // Remove $key for $keys
            foreach ($keys as $k => $v) {
                if ($v === $key) {
                    unset($keys[$k]);
                }
            }

            ++$count;
        }

        self::assertSame(4, $count);
    }

    public function testIsHit()
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        self::assertFalse($item->isHit());
    }

    public function testClear()
    {
        self::assertTrue($this->createCachePool()->clear());
    }

    public function testDeleteItem()
    {
        self::assertTrue($this->createCachePool()->deleteItem('key'));
    }

    public function testDeleteItems()
    {
        self::assertTrue($this->createCachePool()->deleteItems(['key', 'foo', 'bar']));
    }

    public function testSave()
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        self::assertFalse($item->isHit());
        self::assertNull($item->get(), "Item's value must be null when isHit is false.");

        self::assertTrue($adapter->save($item));
    }

    public function testDeferredSave()
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        self::assertFalse($item->isHit());
        self::assertNull($item->get(), "Item's value must be null when isHit is false.");

        self::assertTrue($adapter->saveDeferred($item));
    }

    public function testCommit()
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        self::assertFalse($item->isHit());
        self::assertNull($item->get(), "Item's value must be null when isHit is false.");

        self::assertTrue($adapter->saveDeferred($item));
        self::assertTrue($this->createCachePool()->commit());
    }
}

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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;

#[Group('time-sensitive')]
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

    public function testGet(): void
    {
        $adapter = $this->createCachePool();

        $fetched = [];
        $adapter->get('myKey', static function ($item) use (&$fetched): void { $fetched[] = $item; });
        $this->assertCount(1, $fetched);
        $item = $fetched[0];
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit is false.");
        $this->assertSame('myKey', $item->getKey());
    }

    public function testHasItem(): void
    {
        $this->assertFalse($this->createCachePool()->hasItem('key'));
    }

    public function testGetItems(): void
    {
        $adapter = $this->createCachePool();

        $keys = ['foo', 'bar', 'baz', 'biz'];

        /** @var CacheItemInterface[] $items */
        $items = $adapter->getItems($keys);
        $count = 0;

        foreach ($items as $key => $item) {
            $itemKey = $item->getKey();

            $this->assertEquals($itemKey, $key, 'Keys must be preserved when fetching multiple items');
            $this->assertContains($key, $keys, 'Cache key cannot change.');
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
        $this->assertTrue($this->createCachePool()->deleteItems(['key', 'foo', 'bar']));
    }

    public function testSave(): void
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit is false.");

        $this->assertTrue($adapter->save($item));
    }

    public function testDeferredSave(): void
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit is false.");

        $this->assertTrue($adapter->saveDeferred($item));
    }

    public function testCommit(): void
    {
        $adapter = $this->createCachePool();

        $item = $adapter->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit is false.");

        $this->assertTrue($adapter->saveDeferred($item));
        $this->assertTrue($this->createCachePool()->commit());
    }

    public function testTaggable(): void
    {
        $this->expectNotToPerformAssertions();
        $adapter = $this->createCachePool();
        $item = $adapter->getItem('any_item');
        // No error triggered 'Cache item "%s" comes from a non tag-aware pool: you cannot tag it.'
        $item->tag(['tag1']);
    }

    public function testInvalidateTags(): void
    {
        $adapter = $this->createCachePool();

        self::assertTrue($adapter->invalidateTags(['foo']));
    }
}

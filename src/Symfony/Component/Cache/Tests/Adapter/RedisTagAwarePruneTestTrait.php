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

use Symfony\Component\Cache\PruneableInterface;

trait RedisTagAwarePruneTestTrait
{
    public function testPruneRemovesDanglingReferencesFromTagSets()
    {
        $pool = $this->createCachePool();
        $this->assertInstanceOf(PruneableInterface::class, $pool);

        $tag = 'prune-tag-dangling';
        foreach (['prune-live', 'prune-dead'] as $key) {
            $item = $pool->getItem($key);
            $item->set('value');
            $item->tag($tag);
            $pool->save($item);
        }

        // simulate expiry or eviction happening behind the adapter's back
        self::$redis->del($this->getPruneRawKey('prune-dead'));

        $this->assertTrue($pool->prune());

        $this->assertSame([$this->getPruneRawKey('prune-live')], self::$redis->sMembers($this->getPruneRawTagKey($tag)));
        $this->assertTrue($pool->getItem('prune-live')->isHit());

        $pool->invalidateTags([$tag]);
        $this->assertFalse($pool->getItem('prune-live')->isHit());
    }

    public function testPruneRemovesOrphanedTagSets()
    {
        $pool = $this->createCachePool();
        $this->assertInstanceOf(PruneableInterface::class, $pool);

        $tag = 'prune-tag-orphaned';
        $item = $pool->getItem('prune-orphaned');
        $item->set('value');
        $item->tag($tag);
        $pool->save($item);

        $this->assertSame(1, (int) self::$redis->exists($this->getPruneRawTagKey($tag)));

        self::$redis->del($this->getPruneRawKey('prune-orphaned'));

        $this->assertTrue($pool->prune());

        $this->assertSame(0, (int) self::$redis->exists($this->getPruneRawTagKey($tag)));
    }

    public function testPruneRemovesDanglingReferencesToSubNamespacedItems()
    {
        $pool = $this->createCachePool();
        $this->assertInstanceOf(PruneableInterface::class, $pool);
        $subPool = $pool->withSubNamespace('prune-sub');

        $tag = 'prune-tag-sub';
        $item = $subPool->getItem('prune-sub-item');
        $item->set('value');
        $item->tag($tag);
        $subPool->save($item);

        self::$redis->del($this->getPruneRawKey('prune-sub:prune-sub-item'));

        $this->assertTrue($pool->prune());

        $this->assertSame(0, (int) self::$redis->exists($this->getPruneRawTagKey($tag)));
    }

    public function testPruneSkipsKeysThatAreNotTagSets()
    {
        $pool = $this->createCachePool();
        $this->assertInstanceOf(PruneableInterface::class, $pool);

        $key = "prune-str\1tags\1inside";
        $item = $pool->getItem($key);
        $item->set('value');
        $pool->save($item);

        $this->assertTrue($pool->prune());

        $this->assertTrue($pool->getItem($key)->isHit());

        $pool->deleteItem($key);
    }

    private function getPruneRawKey(string $key): string
    {
        return str_replace('\\', '.', static::class).':'.$key;
    }

    private function getPruneRawTagKey(string $tag): string
    {
        return $this->getPruneRawKey("\1tags\1".$tag);
    }
}

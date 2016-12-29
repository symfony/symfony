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

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;

/**
 * @group time-sensitive
 */
class TraceableAdapterTest extends AdapterTestCase
{
    public function createCachePool($defaultLifetime = 0)
    {
        return new TraceableAdapter(new FilesystemAdapter('', $defaultLifetime));
    }

    public function testGetItemMiss()
    {
        $pool = $this->createCachePool();
        $pool->getItem('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals('getItem', $call->name);
        $this->assertEquals('k', $call->argument);
        $this->assertEquals(0, $call->hits);
        $this->assertEquals(1, $call->misses);
        $this->assertNull($call->result);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testGetItemHit()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('k')->set('foo');
        $pool->save($item);
        $pool->getItem('k');
        $calls = $pool->getCalls();
        $this->assertCount(3, $calls);

        $call = $calls[2];
        $this->assertEquals(1, $call->hits);
        $this->assertEquals(0, $call->misses);
    }

    public function testGetItemsMiss()
    {
        $pool = $this->createCachePool();
        $arg = array('k0', 'k1');
        $items = $pool->getItems($arg);
        foreach ($items as $item) {
        }
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals('getItems', $call->name);
        $this->assertEquals($arg, $call->argument);
        $this->assertEquals(2, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testHasItemMiss()
    {
        $pool = $this->createCachePool();
        $pool->hasItem('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals('hasItem', $call->name);
        $this->assertEquals('k', $call->argument);
        $this->assertFalse($call->result);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testHasItemHit()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('k')->set('foo');
        $pool->save($item);
        $pool->hasItem('k');
        $calls = $pool->getCalls();
        $this->assertCount(3, $calls);

        $call = $calls[2];
        $this->assertEquals('hasItem', $call->name);
        $this->assertEquals('k', $call->argument);
        $this->assertTrue($call->result);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testDeleteItem()
    {
        $pool = $this->createCachePool();
        $pool->deleteItem('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals('deleteItem', $call->name);
        $this->assertEquals('k', $call->argument);
        $this->assertEquals(0, $call->hits);
        $this->assertEquals(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testDeleteItems()
    {
        $pool = $this->createCachePool();
        $arg = array('k0', 'k1');
        $pool->deleteItems($arg);
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals('deleteItems', $call->name);
        $this->assertEquals($arg, $call->argument);
        $this->assertEquals(0, $call->hits);
        $this->assertEquals(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testSave()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('k')->set('foo');
        $pool->save($item);
        $calls = $pool->getCalls();
        $this->assertCount(2, $calls);

        $call = $calls[1];
        $this->assertEquals('save', $call->name);
        $this->assertEquals($item, $call->argument);
        $this->assertEquals(0, $call->hits);
        $this->assertEquals(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testSaveDeferred()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('k')->set('foo');
        $pool->saveDeferred($item);
        $calls = $pool->getCalls();
        $this->assertCount(2, $calls);

        $call = $calls[1];
        $this->assertEquals('saveDeferred', $call->name);
        $this->assertEquals($item, $call->argument);
        $this->assertEquals(0, $call->hits);
        $this->assertEquals(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testCommit()
    {
        $pool = $this->createCachePool();
        $pool->commit();
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals('commit', $call->name);
        $this->assertNull(null, $call->argument);
        $this->assertEquals(0, $call->hits);
        $this->assertEquals(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }
}

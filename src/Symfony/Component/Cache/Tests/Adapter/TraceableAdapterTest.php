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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;

/**
 * @group time-sensitive
 */
class TraceableAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testPrune' => 'TraceableAdapter just proxies',
    ];

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new TraceableAdapter(new FilesystemAdapter('', $defaultLifetime));
    }

    public function testGetItemMissTrace()
    {
        $pool = $this->createCachePool();
        $pool->getItem('k');
        $calls = $pool->getCalls();
        self::assertCount(1, $calls);

        $call = $calls[0];
        self::assertSame('getItem', $call->name);
        self::assertSame(['k' => false], $call->result);
        self::assertSame(0, $call->hits);
        self::assertSame(1, $call->misses);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }

    public function testGetItemHitTrace()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('k')->set('foo');
        $pool->save($item);
        $pool->getItem('k');
        $calls = $pool->getCalls();
        self::assertCount(3, $calls);

        $call = $calls[2];
        self::assertSame(1, $call->hits);
        self::assertSame(0, $call->misses);
    }

    public function testGetItemsMissTrace()
    {
        $pool = $this->createCachePool();
        $arg = ['k0', 'k1'];
        $items = $pool->getItems($arg);
        foreach ($items as $item) {
        }
        $calls = $pool->getCalls();
        self::assertCount(1, $calls);

        $call = $calls[0];
        self::assertSame('getItems', $call->name);
        self::assertSame(['k0' => false, 'k1' => false], $call->result);
        self::assertSame(2, $call->misses);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }

    public function testHasItemMissTrace()
    {
        $pool = $this->createCachePool();
        $pool->hasItem('k');
        $calls = $pool->getCalls();
        self::assertCount(1, $calls);

        $call = $calls[0];
        self::assertSame('hasItem', $call->name);
        self::assertSame(['k' => false], $call->result);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }

    public function testHasItemHitTrace()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('k')->set('foo');
        $pool->save($item);
        $pool->hasItem('k');
        $calls = $pool->getCalls();
        self::assertCount(3, $calls);

        $call = $calls[2];
        self::assertSame('hasItem', $call->name);
        self::assertSame(['k' => true], $call->result);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }

    public function testDeleteItemTrace()
    {
        $pool = $this->createCachePool();
        $pool->deleteItem('k');
        $calls = $pool->getCalls();
        self::assertCount(1, $calls);

        $call = $calls[0];
        self::assertSame('deleteItem', $call->name);
        self::assertSame(['k' => true], $call->result);
        self::assertSame(0, $call->hits);
        self::assertSame(0, $call->misses);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }

    public function testDeleteItemsTrace()
    {
        $pool = $this->createCachePool();
        $arg = ['k0', 'k1'];
        $pool->deleteItems($arg);
        $calls = $pool->getCalls();
        self::assertCount(1, $calls);

        $call = $calls[0];
        self::assertSame('deleteItems', $call->name);
        self::assertSame(['keys' => $arg, 'result' => true], $call->result);
        self::assertSame(0, $call->hits);
        self::assertSame(0, $call->misses);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }

    public function testSaveTrace()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('k')->set('foo');
        $pool->save($item);
        $calls = $pool->getCalls();
        self::assertCount(2, $calls);

        $call = $calls[1];
        self::assertSame('save', $call->name);
        self::assertSame(['k' => true], $call->result);
        self::assertSame(0, $call->hits);
        self::assertSame(0, $call->misses);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }

    public function testSaveDeferredTrace()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('k')->set('foo');
        $pool->saveDeferred($item);
        $calls = $pool->getCalls();
        self::assertCount(2, $calls);

        $call = $calls[1];
        self::assertSame('saveDeferred', $call->name);
        self::assertSame(['k' => true], $call->result);
        self::assertSame(0, $call->hits);
        self::assertSame(0, $call->misses);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }

    public function testCommitTrace()
    {
        $pool = $this->createCachePool();
        $pool->commit();
        $calls = $pool->getCalls();
        self::assertCount(1, $calls);

        $call = $calls[0];
        self::assertSame('commit', $call->name);
        self::assertTrue($call->result);
        self::assertSame(0, $call->hits);
        self::assertSame(0, $call->misses);
        self::assertNotEmpty($call->start);
        self::assertNotEmpty($call->end);
    }
}

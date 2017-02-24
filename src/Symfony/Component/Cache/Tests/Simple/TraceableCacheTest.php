<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\Cache\Simple\TraceableCache;

/**
 * @group time-sensitive
 */
class TraceableCacheTest extends CacheTestCase
{
    public function createSimpleCache($defaultLifetime = 0)
    {
        return new TraceableCache(new FilesystemCache('', $defaultLifetime));
    }

    public function testGetMiss()
    {
        $pool = $this->createSimpleCache();
        $pool->get('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('get', $call->name);
        $this->assertSame(array('key' => 'k', 'default' => null), $call->arguments);
        $this->assertSame(0, $call->hits);
        $this->assertSame(1, $call->misses);
        $this->assertNull($call->result);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testGetHit()
    {
        $pool = $this->createSimpleCache();
        $pool->set('k', 'foo');
        $pool->get('k');
        $calls = $pool->getCalls();
        $this->assertCount(2, $calls);

        $call = $calls[1];
        $this->assertSame(1, $call->hits);
        $this->assertSame(0, $call->misses);
    }

    public function testGetMultipleMiss()
    {
        $pool = $this->createSimpleCache();
        $arg = array('k0', 'k1');
        $values = $pool->getMultiple($arg);
        foreach ($values as $value) {
        }
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('getMultiple', $call->name);
        $this->assertSame(array('keys' => $arg, 'default' => null), $call->arguments);
        $this->assertSame(2, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testHasMiss()
    {
        $pool = $this->createSimpleCache();
        $pool->has('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('has', $call->name);
        $this->assertSame(array('key' => 'k'), $call->arguments);
        $this->assertFalse($call->result);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testHasHit()
    {
        $pool = $this->createSimpleCache();
        $pool->set('k', 'foo');
        $pool->has('k');
        $calls = $pool->getCalls();
        $this->assertCount(2, $calls);

        $call = $calls[1];
        $this->assertSame('has', $call->name);
        $this->assertSame(array('key' => 'k'), $call->arguments);
        $this->assertTrue($call->result);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testDelete()
    {
        $pool = $this->createSimpleCache();
        $pool->delete('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('delete', $call->name);
        $this->assertSame(array('key' => 'k'), $call->arguments);
        $this->assertSame(0, $call->hits);
        $this->assertSame(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testDeleteMultiple()
    {
        $pool = $this->createSimpleCache();
        $arg = array('k0', 'k1');
        $pool->deleteMultiple($arg);
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('deleteMultiple', $call->name);
        $this->assertSame(array('keys' => $arg), $call->arguments);
        $this->assertSame(0, $call->hits);
        $this->assertSame(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testSet()
    {
        $pool = $this->createSimpleCache();
        $pool->set('k', 'foo');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('set', $call->name);
        $this->assertSame(array('key' => 'k', 'value' => 'foo', 'ttl' => null), $call->arguments);
        $this->assertSame(0, $call->hits);
        $this->assertSame(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testSetMultiple()
    {
        $pool = $this->createSimpleCache();
        $pool->setMultiple(array('k' => 'foo'));
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('setMultiple', $call->name);
        $this->assertSame(array('values' => array('k' => 'foo'), 'ttl' => null), $call->arguments);
        $this->assertSame(0, $call->hits);
        $this->assertSame(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }
}

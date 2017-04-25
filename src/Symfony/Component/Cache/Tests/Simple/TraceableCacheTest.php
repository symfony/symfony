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

    public function testGetMissTrace()
    {
        $pool = $this->createSimpleCache();
        $pool->get('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('get', $call->name);
        $this->assertSame(array('k' => false), $call->result);
        $this->assertSame(0, $call->hits);
        $this->assertSame(1, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testGetHitTrace()
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

    public function testGetMultipleMissTrace()
    {
        $pool = $this->createSimpleCache();
        $pool->set('k1', 123);
        $values = $pool->getMultiple(array('k0', 'k1'));
        foreach ($values as $value) {
        }
        $calls = $pool->getCalls();
        $this->assertCount(2, $calls);

        $call = $calls[1];
        $this->assertSame('getMultiple', $call->name);
        $this->assertSame(array('k1' => true, 'k0' => false), $call->result);
        $this->assertSame(1, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testHasMissTrace()
    {
        $pool = $this->createSimpleCache();
        $pool->has('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('has', $call->name);
        $this->assertSame(array('k' => false), $call->result);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testHasHitTrace()
    {
        $pool = $this->createSimpleCache();
        $pool->set('k', 'foo');
        $pool->has('k');
        $calls = $pool->getCalls();
        $this->assertCount(2, $calls);

        $call = $calls[1];
        $this->assertSame('has', $call->name);
        $this->assertSame(array('k' => true), $call->result);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testDeleteTrace()
    {
        $pool = $this->createSimpleCache();
        $pool->delete('k');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('delete', $call->name);
        $this->assertSame(array('k' => true), $call->result);
        $this->assertSame(0, $call->hits);
        $this->assertSame(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testDeleteMultipleTrace()
    {
        $pool = $this->createSimpleCache();
        $arg = array('k0', 'k1');
        $pool->deleteMultiple($arg);
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('deleteMultiple', $call->name);
        $this->assertSame(array('keys' => $arg, 'result' => true), $call->result);
        $this->assertSame(0, $call->hits);
        $this->assertSame(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testTraceSetTrace()
    {
        $pool = $this->createSimpleCache();
        $pool->set('k', 'foo');
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('set', $call->name);
        $this->assertSame(array('k' => true), $call->result);
        $this->assertSame(0, $call->hits);
        $this->assertSame(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }

    public function testSetMultipleTrace()
    {
        $pool = $this->createSimpleCache();
        $pool->setMultiple(array('k' => 'foo'));
        $calls = $pool->getCalls();
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertSame('setMultiple', $call->name);
        $this->assertSame(array('keys' => array('k'), 'result' => true), $call->result);
        $this->assertSame(0, $call->hits);
        $this->assertSame(0, $call->misses);
        $this->assertNotEmpty($call->start);
        $this->assertNotEmpty($call->end);
    }
}

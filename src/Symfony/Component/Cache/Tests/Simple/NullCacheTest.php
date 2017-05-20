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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Simple\NullCache;

/**
 * @group time-sensitive
 */
class NullCacheTest extends TestCase
{
    public function createCachePool()
    {
        return new NullCache();
    }

    public function testGetItem()
    {
        $cache = $this->createCachePool();

        $this->assertNull($cache->get('key'));
    }

    public function testHas()
    {
        $this->assertFalse($this->createCachePool()->has('key'));
    }

    public function testGetMultiple()
    {
        $cache = $this->createCachePool();

        $keys = array('foo', 'bar', 'baz', 'biz');

        $default = new \stdClass();
        $items = $cache->getMultiple($keys, $default);
        $count = 0;

        foreach ($items as $key => $item) {
            $this->assertTrue(in_array($key, $keys), 'Cache key can not change.');
            $this->assertSame($default, $item);

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

    public function testClear()
    {
        $this->assertTrue($this->createCachePool()->clear());
    }

    public function testDelete()
    {
        $this->assertTrue($this->createCachePool()->delete('key'));
    }

    public function testDeleteMultiple()
    {
        $this->assertTrue($this->createCachePool()->deleteMultiple(array('key', 'foo', 'bar')));
    }

    public function testSet()
    {
        $cache = $this->createCachePool();

        $this->assertFalse($cache->set('key', 'val'));
        $this->assertNull($cache->get('key'));
    }

    public function testSetMultiple()
    {
        $cache = $this->createCachePool();

        $this->assertFalse($cache->setMultiple(array('key' => 'val')));
        $this->assertNull($cache->get('key'));
    }
}

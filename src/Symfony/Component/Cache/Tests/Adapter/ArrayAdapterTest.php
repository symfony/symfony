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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Tests\Fixtures\TestEnum;
use Symfony\Component\Clock\MockClock;

/**
 * @group time-sensitive
 */
class ArrayAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testGetMetadata' => 'ArrayAdapter does not keep metadata.',
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayAdapter is not.',
    ];

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new ArrayAdapter($defaultLifetime);
    }

    public function testGetValuesHitAndMiss()
    {
        /** @var ArrayAdapter $cache */
        $cache = $this->createCachePool();

        // Hit
        $item = $cache->getItem('foo');
        $item->set('::4711');
        $cache->save($item);

        $fooItem = $cache->getItem('foo');
        $this->assertTrue($fooItem->isHit());
        $this->assertEquals('::4711', $fooItem->get());

        // Miss (should be present as NULL in $values)
        $cache->getItem('bar');

        // Fail (should be missing from $values)
        $item = $cache->getItem('buz');
        $cache->save($item->set(function () {}));

        $values = $cache->getValues();

        $this->assertCount(2, $values);
        $this->assertArrayHasKey('foo', $values);
        $this->assertSame(serialize('::4711'), $values['foo']);
        $this->assertArrayHasKey('bar', $values);
        $this->assertNull($values['bar']);
    }

    public function testMaxLifetime()
    {
        $cache = new ArrayAdapter(0, false, 1);

        $item = $cache->getItem('foo');
        $item->expiresAfter(2);
        $cache->save($item->set(123));

        $this->assertTrue($cache->hasItem('foo'));
        sleep(1);
        $this->assertFalse($cache->hasItem('foo'));
    }

    public function testMaxItems()
    {
        $cache = new ArrayAdapter(0, false, 0, 2);

        $cache->save($cache->getItem('foo'));
        $cache->save($cache->getItem('bar'));
        $cache->save($cache->getItem('buz'));

        $this->assertFalse($cache->hasItem('foo'));
        $this->assertTrue($cache->hasItem('bar'));
        $this->assertTrue($cache->hasItem('buz'));

        $cache->save($cache->getItem('foo'));

        $this->assertFalse($cache->hasItem('bar'));
        $this->assertTrue($cache->hasItem('buz'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testEnum()
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('foo');
        $item->set(TestEnum::Foo);
        $cache->save($item);

        $this->assertSame(TestEnum::Foo, $cache->getItem('foo')->get());
    }

    public function testClockAware()
    {
        $clock = new MockClock();
        $cache = new ArrayAdapter(10, false, 0, 0, $clock);

        $cache->save($cache->getItem('foo'));
        $this->assertTrue($cache->hasItem('foo'));

        $clock->modify('+11 seconds');

        $this->assertFalse($cache->hasItem('foo'));
    }
}

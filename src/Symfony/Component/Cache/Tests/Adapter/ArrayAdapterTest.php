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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Tests\Fixtures\TestEnum;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\VarExporter\DeepCloner;

#[Group('time-sensitive')]
class ArrayAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testGetMetadata' => 'ArrayAdapter does not keep metadata.',
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testClearWithInvalidPrefix' => 'ArrayAdapter does not validate the prefix.',
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
        $cache->save($item->set(static function () {}));

        $values = $cache->getValues();

        $this->assertCount(2, $values);
        $this->assertArrayHasKey('foo', $values);
        $this->assertSame(serialize('::4711'), $values['foo']);
        $this->assertArrayHasKey('bar', $values);
        $this->assertNull($values['bar']);
    }

    public function testGetValuesWithNamedClosure()
    {
        /** @var ArrayAdapter $cache */
        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $item->set([strlen(...)]);
        $cache->save($item);

        // a Closure cannot be serialized: getValues() skips the entry instead of
        // throwing, while raw mode keeps it as a DeepCloner that can be cloned back
        $this->assertArrayNotHasKey('foo', $cache->getValues());

        $raw = $cache->getValues(true);
        $this->assertInstanceOf(DeepCloner::class, $raw['foo']);
        $this->assertInstanceOf(\Closure::class, $raw['foo']->clone(null, true)[0]);
    }

    public function testGetValuesDistinguishesRecordedNullFromMiss()
    {
        /** @var ArrayAdapter $cache */
        $cache = $this->createCachePool();

        // a deliberately cached null is a hit...
        $cache->save($cache->getItem('recorded')->set(null));
        $this->assertTrue($cache->getItem('recorded')->isHit());
        // ...while a looked-up but absent key is a tracked miss
        $this->assertFalse($cache->getItem('miss')->isHit());

        // getValues() keeps them apart: the recorded null is serialized, the miss stays a bare null
        $values = $cache->getValues();
        $this->assertSame(serialize(null), $values['recorded']);
        $this->assertNull($values['miss']);

        // same in raw mode: the recorded null is wrapped in a DeepCloner
        $raw = $cache->getValues(true);
        $this->assertInstanceOf(DeepCloner::class, $raw['recorded']);
        $this->assertNull($raw['miss']);
    }

    public function testGetValuesKeepsPlainStaticValuesUnwrapped()
    {
        /** @var ArrayAdapter $cache */
        $cache = $this->createCachePool();

        $cache->save($cache->getItem('plain')->set('no-colon'));
        $cache->save($cache->getItem('int')->set(42));
        $cache->save($cache->getItem('colon')->set('a:b'));

        $raw = $cache->getValues(true);
        // plain static values stay unwrapped for performance
        $this->assertSame('no-colon', $raw['plain']);
        $this->assertSame(42, $raw['int']);
        // a string holding a colon is wrapped so it can't be mistaken for a serialized value
        $this->assertInstanceOf(DeepCloner::class, $raw['colon']);
        $this->assertSame('a:b', $raw['colon']->clone());
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

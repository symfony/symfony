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
        self::assertTrue($fooItem->isHit());
        self::assertEquals('::4711', $fooItem->get());

        // Miss (should be present as NULL in $values)
        $cache->getItem('bar');

        // Fail (should be missing from $values)
        $item = $cache->getItem('buz');
        $cache->save($item->set(function () {}));

        $values = $cache->getValues();

        self::assertCount(2, $values);
        self::assertArrayHasKey('foo', $values);
        self::assertSame(serialize('::4711'), $values['foo']);
        self::assertArrayHasKey('bar', $values);
        self::assertNull($values['bar']);
    }

    public function testMaxLifetime()
    {
        $cache = new ArrayAdapter(0, false, 1);

        $item = $cache->getItem('foo');
        $item->expiresAfter(2);
        $cache->save($item->set(123));

        self::assertTrue($cache->hasItem('foo'));
        sleep(1);
        self::assertFalse($cache->hasItem('foo'));
    }

    public function testMaxItems()
    {
        $cache = new ArrayAdapter(0, false, 0, 2);

        $cache->save($cache->getItem('foo'));
        $cache->save($cache->getItem('bar'));
        $cache->save($cache->getItem('buz'));

        self::assertFalse($cache->hasItem('foo'));
        self::assertTrue($cache->hasItem('bar'));
        self::assertTrue($cache->hasItem('buz'));

        $cache->save($cache->getItem('foo'));

        self::assertFalse($cache->hasItem('bar'));
        self::assertTrue($cache->hasItem('buz'));
        self::assertTrue($cache->hasItem('foo'));
    }
}

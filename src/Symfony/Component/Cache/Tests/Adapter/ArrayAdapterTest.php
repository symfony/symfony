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
        $this->assertTrue($fooItem->isHit());
        $this->assertEquals('::4711', $fooItem->get());

        // Miss (should be present as NULL in $values)
        $cache->getItem('bar');

        $values = $cache->getValues();

        $this->assertCount(2, $values);
        $this->assertArrayHasKey('foo', $values);
        $this->assertSame(serialize('::4711'), $values['foo']);
        $this->assertArrayHasKey('bar', $values);
        $this->assertNull($values['bar']);
    }
}

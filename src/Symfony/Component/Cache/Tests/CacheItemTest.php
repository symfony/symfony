<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\CacheItem;

class CacheItemTest extends TestCase
{
    public function testValidKey()
    {
        $this->assertSame('foo', CacheItem::validateKey('foo'));
    }

    /**
     * @dataProvider provideInvalidKey
     */
    public function testInvalidKey($key)
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Cache key');
        CacheItem::validateKey($key);
    }

    public function provideInvalidKey()
    {
        return [
            [''],
            ['{'],
            ['}'],
            ['('],
            [')'],
            ['/'],
            ['\\'],
            ['@'],
            [':'],
            [true],
            [null],
            [1],
            [1.1],
            [[[]]],
            [new \Exception('foo')],
        ];
    }

    public function testTag()
    {
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'isTaggable');
        $r->setAccessible(true);
        $r->setValue($item, true);

        $this->assertSame($item, $item->tag('foo'));
        $this->assertSame($item, $item->tag(['bar', 'baz']));

        (\Closure::bind(function () use ($item) {
            $this->assertSame(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'], $item->newMetadata[CacheItem::METADATA_TAGS]);
        }, $this, CacheItem::class))();
    }

    /**
     * @dataProvider provideInvalidKey
     */
    public function testInvalidTag($tag)
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Cache tag');
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'isTaggable');
        $r->setAccessible(true);
        $r->setValue($item, true);

        $item->tag($tag);
    }

    public function testNonTaggableItem()
    {
        $this->expectException('Symfony\Component\Cache\Exception\LogicException');
        $this->expectExceptionMessage('Cache item "foo" comes from a non tag-aware pool: you cannot tag it.');
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'key');
        $r->setAccessible(true);
        $r->setValue($item, 'foo');

        $item->tag([]);
    }
}

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
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Component\Cache\Tests\Fixtures\StringableTag;

class CacheItemTest extends TestCase
{
    public function testValidKey()
    {
        self::assertSame('foo', CacheItem::validateKey('foo'));
    }

    /**
     * @dataProvider provideInvalidKey
     */
    public function testInvalidKey($key)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Cache key');
        CacheItem::validateKey($key);
    }

    public function provideInvalidKey(): array
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

        self::assertSame($item, $item->tag('foo'));
        self::assertSame($item, $item->tag(['bar', 'baz']));
        self::assertSame($item, $item->tag(new StringableTag('qux')));
        self::assertSame($item, $item->tag([new StringableTag('quux'), new StringableTag('quuux')]));

        (\Closure::bind(function () use ($item) {
            $this->assertSame(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz', 'qux' => 'qux', 'quux' => 'quux', 'quuux' => 'quuux'], $item->newMetadata[CacheItem::METADATA_TAGS]);
        }, $this, CacheItem::class))();
    }

    /**
     * @dataProvider provideInvalidKey
     */
    public function testInvalidTag($tag)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Cache tag');
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'isTaggable');
        $r->setAccessible(true);
        $r->setValue($item, true);

        $item->tag($tag);
    }

    public function testNonTaggableItem()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Cache item "foo" comes from a non tag-aware pool: you cannot tag it.');
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'key');
        $r->setAccessible(true);
        $r->setValue($item, 'foo');

        $item->tag([]);
    }
}

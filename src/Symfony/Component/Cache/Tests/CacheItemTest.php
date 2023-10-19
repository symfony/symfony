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
        $this->assertSame('foo', CacheItem::validateKey('foo'));
    }

    /**
     * @dataProvider provideInvalidKey
     */
    public function testInvalidKey($key)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache key');
        CacheItem::validateKey($key);
    }

    public static function provideInvalidKey(): array
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
        $r->setValue($item, true);

        $this->assertSame($item, $item->tag('foo'));
        $this->assertSame($item, $item->tag(['bar', 'baz']));
        $this->assertSame($item, $item->tag(new StringableTag('qux')));
        $this->assertSame($item, $item->tag([new StringableTag('quux'), new StringableTag('quuux')]));

        (\Closure::bind(function () use ($item) {
            $this->assertSame(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz', 'qux' => 'qux', 'quux' => 'quux', 'quuux' => 'quuux'], $item->newMetadata[CacheItem::METADATA_TAGS]);
        }, $this, CacheItem::class))();
    }

    /**
     * @dataProvider provideInvalidKey
     */
    public function testInvalidTag($tag)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache tag');
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'isTaggable');
        $r->setValue($item, true);

        $item->tag($tag);
    }

    public function testNonTaggableItem()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cache item "foo" comes from a non tag-aware pool: you cannot tag it.');
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'key');
        $r->setValue($item, 'foo');

        $item->tag([]);
    }
}

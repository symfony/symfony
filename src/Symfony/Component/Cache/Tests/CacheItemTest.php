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
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage Cache key
     */
    public function testInvalidKey($key)
    {
        CacheItem::validateKey($key);
    }

    public function provideInvalidKey()
    {
        return array(
            array(''),
            array('{'),
            array('}'),
            array('('),
            array(')'),
            array('/'),
            array('\\'),
            array('@'),
            array(':'),
            array(true),
            array(null),
            array(1),
            array(1.1),
            array(array(array())),
            array(new \Exception('foo')),
        );
    }

    public function testTag()
    {
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'isTaggable');
        $r->setAccessible(true);
        $r->setValue($item, true);

        $this->assertSame($item, $item->tag('foo'));
        $this->assertSame($item, $item->tag(array('bar', 'baz')));

        \call_user_func(\Closure::bind(function () use ($item) {
            $this->assertSame(array('foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'), $item->newMetadata[CacheItem::METADATA_TAGS]);
        }, $this, CacheItem::class));
    }

    /**
     * @dataProvider provideInvalidKey
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage Cache tag
     */
    public function testInvalidTag($tag)
    {
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'isTaggable');
        $r->setAccessible(true);
        $r->setValue($item, true);

        $item->tag($tag);
    }

    /**
     * @expectedException \Symfony\Component\Cache\Exception\LogicException
     * @expectedExceptionMessage Cache item "foo" comes from a non tag-aware pool: you cannot tag it.
     */
    public function testNonTaggableItem()
    {
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'key');
        $r->setAccessible(true);
        $r->setValue($item, 'foo');

        $item->tag(array());
    }
}

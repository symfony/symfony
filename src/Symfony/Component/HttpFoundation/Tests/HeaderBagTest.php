<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;

class HeaderBagTest extends TestCase
{
    public function testConstructor()
    {
        $bag = new HeaderBag(['foo' => 'bar']);
        self::assertTrue($bag->has('foo'));
    }

    public function testToStringNull()
    {
        $bag = new HeaderBag();
        self::assertEquals('', $bag->__toString());
    }

    public function testToStringNotNull()
    {
        $bag = new HeaderBag(['foo' => 'bar']);
        self::assertEquals("Foo: bar\r\n", $bag->__toString());
    }

    public function testKeys()
    {
        $bag = new HeaderBag(['foo' => 'bar']);
        $keys = $bag->keys();
        self::assertEquals('foo', $keys[0]);
    }

    public function testGetDate()
    {
        $bag = new HeaderBag(['foo' => 'Tue, 4 Sep 2012 20:00:00 +0200']);
        $headerDate = $bag->getDate('foo');
        self::assertInstanceOf(\DateTime::class, $headerDate);
    }

    public function testGetDateNull()
    {
        $bag = new HeaderBag(['foo' => null]);
        $headerDate = $bag->getDate('foo');
        self::assertNull($headerDate);
    }

    public function testGetDateException()
    {
        self::expectException(\RuntimeException::class);
        $bag = new HeaderBag(['foo' => 'Tue']);
        $bag->getDate('foo');
    }

    public function testGetCacheControlHeader()
    {
        $bag = new HeaderBag();
        $bag->addCacheControlDirective('public', '#a');
        self::assertTrue($bag->hasCacheControlDirective('public'));
        self::assertEquals('#a', $bag->getCacheControlDirective('public'));
    }

    public function testAll()
    {
        $bag = new HeaderBag(['foo' => 'bar']);
        self::assertEquals(['foo' => ['bar']], $bag->all(), '->all() gets all the input');

        $bag = new HeaderBag(['FOO' => 'BAR']);
        self::assertEquals(['foo' => ['BAR']], $bag->all(), '->all() gets all the input key are lower case');
    }

    public function testReplace()
    {
        $bag = new HeaderBag(['foo' => 'bar']);

        $bag->replace(['NOPE' => 'BAR']);
        self::assertEquals(['nope' => ['BAR']], $bag->all(), '->replace() replaces the input with the argument');
        self::assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
    }

    public function testGet()
    {
        $bag = new HeaderBag(['foo' => 'bar', 'fuzz' => 'bizz']);
        self::assertEquals('bar', $bag->get('foo'), '->get return current value');
        self::assertEquals('bar', $bag->get('FoO'), '->get key in case insensitive');
        self::assertEquals(['bar'], $bag->all('foo'), '->get return the value as array');

        // defaults
        self::assertNull($bag->get('none'), '->get unknown values returns null');
        self::assertEquals('default', $bag->get('none', 'default'), '->get unknown values returns default');
        self::assertEquals([], $bag->all('none'), '->get unknown values returns an empty array');

        $bag->set('foo', 'bor', false);
        self::assertEquals('bar', $bag->get('foo'), '->get return first value');
        self::assertEquals(['bar', 'bor'], $bag->all('foo'), '->get return all values as array');

        $bag->set('baz', null);
        self::assertNull($bag->get('baz', 'nope'), '->get return null although different default value is given');
    }

    public function testSetAssociativeArray()
    {
        $bag = new HeaderBag();
        $bag->set('foo', ['bad-assoc-index' => 'value']);
        self::assertSame('value', $bag->get('foo'));
        self::assertSame(['value'], $bag->all('foo'), 'assoc indices of multi-valued headers are ignored');
    }

    public function testContains()
    {
        $bag = new HeaderBag(['foo' => 'bar', 'fuzz' => 'bizz']);
        self::assertTrue($bag->contains('foo', 'bar'), '->contains first value');
        self::assertTrue($bag->contains('fuzz', 'bizz'), '->contains second value');
        self::assertFalse($bag->contains('nope', 'nope'), '->contains unknown value');
        self::assertFalse($bag->contains('foo', 'nope'), '->contains unknown value');

        // Multiple values
        $bag->set('foo', 'bor', false);
        self::assertTrue($bag->contains('foo', 'bar'), '->contains first value');
        self::assertTrue($bag->contains('foo', 'bor'), '->contains second value');
        self::assertFalse($bag->contains('foo', 'nope'), '->contains unknown value');
    }

    public function testCacheControlDirectiveAccessors()
    {
        $bag = new HeaderBag();
        $bag->addCacheControlDirective('public');

        self::assertTrue($bag->hasCacheControlDirective('public'));
        self::assertTrue($bag->getCacheControlDirective('public'));
        self::assertEquals('public', $bag->get('cache-control'));

        $bag->addCacheControlDirective('max-age', 10);
        self::assertTrue($bag->hasCacheControlDirective('max-age'));
        self::assertEquals(10, $bag->getCacheControlDirective('max-age'));
        self::assertEquals('max-age=10, public', $bag->get('cache-control'));

        $bag->removeCacheControlDirective('max-age');
        self::assertFalse($bag->hasCacheControlDirective('max-age'));
    }

    public function testCacheControlDirectiveParsing()
    {
        $bag = new HeaderBag(['cache-control' => 'public, max-age=10']);
        self::assertTrue($bag->hasCacheControlDirective('public'));
        self::assertTrue($bag->getCacheControlDirective('public'));

        self::assertTrue($bag->hasCacheControlDirective('max-age'));
        self::assertEquals(10, $bag->getCacheControlDirective('max-age'));

        $bag->addCacheControlDirective('s-maxage', 100);
        self::assertEquals('max-age=10, public, s-maxage=100', $bag->get('cache-control'));
    }

    public function testCacheControlDirectiveParsingQuotedZero()
    {
        $bag = new HeaderBag(['cache-control' => 'max-age="0"']);
        self::assertTrue($bag->hasCacheControlDirective('max-age'));
        self::assertEquals(0, $bag->getCacheControlDirective('max-age'));
    }

    public function testCacheControlDirectiveOverrideWithReplace()
    {
        $bag = new HeaderBag(['cache-control' => 'private, max-age=100']);
        $bag->replace(['cache-control' => 'public, max-age=10']);
        self::assertTrue($bag->hasCacheControlDirective('public'));
        self::assertTrue($bag->getCacheControlDirective('public'));

        self::assertTrue($bag->hasCacheControlDirective('max-age'));
        self::assertEquals(10, $bag->getCacheControlDirective('max-age'));
    }

    public function testCacheControlClone()
    {
        $headers = ['foo' => 'bar'];
        $bag1 = new HeaderBag($headers);
        $bag2 = new HeaderBag($bag1->all());

        self::assertEquals($bag1->all(), $bag2->all());
    }

    public function testGetIterator()
    {
        $headers = ['foo' => 'bar', 'hello' => 'world', 'third' => 'charm'];
        $headerBag = new HeaderBag($headers);

        $i = 0;
        foreach ($headerBag as $key => $val) {
            ++$i;
            self::assertEquals([$headers[$key]], $val);
        }

        self::assertEquals(\count($headers), $i);
    }

    public function testCount()
    {
        $headers = ['foo' => 'bar', 'HELLO' => 'WORLD'];
        $headerBag = new HeaderBag($headers);

        self::assertCount(\count($headers), $headerBag);
    }
}

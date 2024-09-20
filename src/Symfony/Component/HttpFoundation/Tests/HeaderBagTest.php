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
        $this->assertTrue($bag->has('foo'));
    }

    public function testToStringNull()
    {
        $bag = new HeaderBag();
        $this->assertEquals('', $bag->__toString());
    }

    public function testToStringNotNull()
    {
        $bag = new HeaderBag(['foo' => 'bar']);
        $this->assertEquals("Foo: bar\r\n", $bag->__toString());
    }

    public function testKeys()
    {
        $bag = new HeaderBag(['foo' => 'bar']);
        $keys = $bag->keys();
        $this->assertEquals('foo', $keys[0]);
    }

    public function testGetDate()
    {
        $bag = new HeaderBag(['foo' => 'Tue, 4 Sep 2012 20:00:00 +0200']);
        $headerDate = $bag->getDate('foo');
        $this->assertInstanceOf(\DateTimeImmutable::class, $headerDate);
    }

    public function testGetDateNull()
    {
        $bag = new HeaderBag(['foo' => null]);
        $headerDate = $bag->getDate('foo');
        $this->assertNull($headerDate);
    }

    public function testGetDateException()
    {
        $this->expectException(\RuntimeException::class);
        $bag = new HeaderBag(['foo' => 'Tue']);
        $bag->getDate('foo');
    }

    public function testGetCacheControlHeader()
    {
        $bag = new HeaderBag();
        $bag->addCacheControlDirective('public', '#a');
        $this->assertTrue($bag->hasCacheControlDirective('public'));
        $this->assertEquals('#a', $bag->getCacheControlDirective('public'));
    }

    public function testAll()
    {
        $bag = new HeaderBag(['foo' => 'bar']);
        $this->assertEquals(['foo' => ['bar']], $bag->all(), '->all() gets all the input');

        $bag = new HeaderBag(['FOO' => 'BAR']);
        $this->assertEquals(['foo' => ['BAR']], $bag->all(), '->all() gets all the input key are lower case');
    }

    public function testReplace()
    {
        $bag = new HeaderBag(['foo' => 'bar']);

        $bag->replace(['NOPE' => 'BAR']);
        $this->assertEquals(['nope' => ['BAR']], $bag->all(), '->replace() replaces the input with the argument');
        $this->assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
    }

    public function testGet()
    {
        $bag = new HeaderBag(['foo' => 'bar', 'fuzz' => 'bizz']);
        $this->assertEquals('bar', $bag->get('foo'), '->get return current value');
        $this->assertEquals('bar', $bag->get('FoO'), '->get key in case-insensitive');
        $this->assertEquals(['bar'], $bag->all('foo'), '->get return the value as array');

        // defaults
        $this->assertNull($bag->get('none'), '->get unknown values returns null');
        $this->assertEquals('default', $bag->get('none', 'default'), '->get unknown values returns default');
        $this->assertEquals([], $bag->all('none'), '->get unknown values returns an empty array');

        $bag->set('foo', 'bor', false);
        $this->assertEquals('bar', $bag->get('foo'), '->get return first value');
        $this->assertEquals(['bar', 'bor'], $bag->all('foo'), '->get return all values as array');

        $bag->set('baz', null);
        $this->assertNull($bag->get('baz', 'nope'), '->get return null although different default value is given');
    }

    public function testSetAssociativeArray()
    {
        $bag = new HeaderBag();
        $bag->set('foo', ['bad-assoc-index' => 'value']);
        $this->assertSame('value', $bag->get('foo'));
        $this->assertSame(['value'], $bag->all('foo'), 'assoc indices of multi-valued headers are ignored');
    }

    public function testContains()
    {
        $bag = new HeaderBag(['foo' => 'bar', 'fuzz' => 'bizz']);
        $this->assertTrue($bag->contains('foo', 'bar'), '->contains first value');
        $this->assertTrue($bag->contains('fuzz', 'bizz'), '->contains second value');
        $this->assertFalse($bag->contains('nope', 'nope'), '->contains unknown value');
        $this->assertFalse($bag->contains('foo', 'nope'), '->contains unknown value');

        // Multiple values
        $bag->set('foo', 'bor', false);
        $this->assertTrue($bag->contains('foo', 'bar'), '->contains first value');
        $this->assertTrue($bag->contains('foo', 'bor'), '->contains second value');
        $this->assertFalse($bag->contains('foo', 'nope'), '->contains unknown value');
    }

    public function testCacheControlDirectiveAccessors()
    {
        $bag = new HeaderBag();
        $bag->addCacheControlDirective('public');

        $this->assertTrue($bag->hasCacheControlDirective('public'));
        $this->assertTrue($bag->getCacheControlDirective('public'));
        $this->assertEquals('public', $bag->get('cache-control'));

        $bag->addCacheControlDirective('max-age', 10);
        $this->assertTrue($bag->hasCacheControlDirective('max-age'));
        $this->assertEquals(10, $bag->getCacheControlDirective('max-age'));
        $this->assertEquals('max-age=10, public', $bag->get('cache-control'));

        $bag->removeCacheControlDirective('max-age');
        $this->assertFalse($bag->hasCacheControlDirective('max-age'));
    }

    public function testCacheControlDirectiveParsing()
    {
        $bag = new HeaderBag(['cache-control' => 'public, max-age=10']);
        $this->assertTrue($bag->hasCacheControlDirective('public'));
        $this->assertTrue($bag->getCacheControlDirective('public'));

        $this->assertTrue($bag->hasCacheControlDirective('max-age'));
        $this->assertEquals(10, $bag->getCacheControlDirective('max-age'));

        $bag->addCacheControlDirective('s-maxage', 100);
        $this->assertEquals('max-age=10, public, s-maxage=100', $bag->get('cache-control'));
    }

    public function testCacheControlDirectiveParsingQuotedZero()
    {
        $bag = new HeaderBag(['cache-control' => 'max-age="0"']);
        $this->assertTrue($bag->hasCacheControlDirective('max-age'));
        $this->assertEquals(0, $bag->getCacheControlDirective('max-age'));
    }

    public function testCacheControlDirectiveOverrideWithReplace()
    {
        $bag = new HeaderBag(['cache-control' => 'private, max-age=100']);
        $bag->replace(['cache-control' => 'public, max-age=10']);
        $this->assertTrue($bag->hasCacheControlDirective('public'));
        $this->assertTrue($bag->getCacheControlDirective('public'));

        $this->assertTrue($bag->hasCacheControlDirective('max-age'));
        $this->assertEquals(10, $bag->getCacheControlDirective('max-age'));
    }

    public function testCacheControlClone()
    {
        $headers = ['foo' => 'bar'];
        $bag1 = new HeaderBag($headers);
        $bag2 = new HeaderBag($bag1->all());

        $this->assertEquals($bag1->all(), $bag2->all());
    }

    public function testGetIterator()
    {
        $headers = ['foo' => 'bar', 'hello' => 'world', 'third' => 'charm'];
        $headerBag = new HeaderBag($headers);

        $i = 0;
        foreach ($headerBag as $key => $val) {
            ++$i;
            $this->assertEquals([$headers[$key]], $val);
        }

        $this->assertEquals(\count($headers), $i);
    }

    public function testCount()
    {
        $headers = ['foo' => 'bar', 'HELLO' => 'WORLD'];
        $headerBag = new HeaderBag($headers);

        $this->assertCount(\count($headers), $headerBag);
    }
}

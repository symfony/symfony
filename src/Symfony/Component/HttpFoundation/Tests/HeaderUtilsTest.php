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
use Symfony\Component\HttpFoundation\HeaderUtils;

class HeaderUtilsTest extends TestCase
{
    public function testSplit()
    {
        $this->assertSame(array('foo=123', 'bar'), HeaderUtils::split('foo=123,bar', ','));
        $this->assertSame(array('foo=123', 'bar'), HeaderUtils::split('foo=123, bar', ','));
        $this->assertSame(array(array('foo=123', 'bar')), HeaderUtils::split('foo=123; bar', ',;'));
        $this->assertSame(array(array('foo=123'), array('bar')), HeaderUtils::split('foo=123, bar', ',;'));
        $this->assertSame(array('foo', '123, bar'), HeaderUtils::split('foo=123, bar', '='));
        $this->assertSame(array('foo', '123, bar'), HeaderUtils::split(' foo = 123, bar ', '='));
        $this->assertSame(array(array('foo', '123'), array('bar')), HeaderUtils::split('foo=123, bar', ',='));
        $this->assertSame(array(array(array('foo', '123')), array(array('bar'), array('foo', '456'))), HeaderUtils::split('foo=123, bar; foo=456', ',;='));
        $this->assertSame(array(array(array('foo', 'a,b;c=d'))), HeaderUtils::split('foo="a,b;c=d"', ',;='));

        $this->assertSame(array('foo', 'bar'), HeaderUtils::split('foo,,,, bar', ','));
        $this->assertSame(array('foo', 'bar'), HeaderUtils::split(',foo, bar,', ','));
        $this->assertSame(array('foo', 'bar'), HeaderUtils::split(' , foo, bar, ', ','));
        $this->assertSame(array('foo bar'), HeaderUtils::split('foo "bar"', ','));
        $this->assertSame(array('foo bar'), HeaderUtils::split('"foo" bar', ','));
        $this->assertSame(array('foo bar'), HeaderUtils::split('"foo" "bar"', ','));

        // These are not a valid header values. We test that they parse anyway,
        // and that both the valid and invalid parts are returned.
        $this->assertSame(array(), HeaderUtils::split('', ','));
        $this->assertSame(array(), HeaderUtils::split(',,,', ','));
        $this->assertSame(array('foo', 'bar', 'baz'), HeaderUtils::split('foo, "bar", "baz', ','));
        $this->assertSame(array('foo', 'bar, baz'), HeaderUtils::split('foo, "bar, baz', ','));
        $this->assertSame(array('foo', 'bar, baz\\'), HeaderUtils::split('foo, "bar, baz\\', ','));
        $this->assertSame(array('foo', 'bar, baz\\'), HeaderUtils::split('foo, "bar, baz\\\\', ','));
    }

    public function testCombine()
    {
        $this->assertSame(array('foo' => '123'), HeaderUtils::combine(array(array('foo', '123'))));
        $this->assertSame(array('foo' => true), HeaderUtils::combine(array(array('foo'))));
        $this->assertSame(array('foo' => true), HeaderUtils::combine(array(array('Foo'))));
        $this->assertSame(array('foo' => '123', 'bar' => true), HeaderUtils::combine(array(array('foo', '123'), array('bar'))));
    }

    public function testToString()
    {
        $this->assertSame('foo', HeaderUtils::toString(array('foo' => true), ','));
        $this->assertSame('foo; bar', HeaderUtils::toString(array('foo' => true, 'bar' => true), ';'));
        $this->assertSame('foo=123', HeaderUtils::toString(array('foo' => '123'), ','));
        $this->assertSame('foo="1 2 3"', HeaderUtils::toString(array('foo' => '1 2 3'), ','));
        $this->assertSame('foo="1 2 3", bar', HeaderUtils::toString(array('foo' => '1 2 3', 'bar' => true), ','));
    }

    public function testQuote()
    {
        $this->assertSame('foo', HeaderUtils::quote('foo'));
        $this->assertSame('az09!#$%&\'*.^_`|~-', HeaderUtils::quote('az09!#$%&\'*.^_`|~-'));
        $this->assertSame('"foo bar"', HeaderUtils::quote('foo bar'));
        $this->assertSame('"foo [bar]"', HeaderUtils::quote('foo [bar]'));
        $this->assertSame('"foo \"bar\""', HeaderUtils::quote('foo "bar"'));
        $this->assertSame('"foo \\\\ bar"', HeaderUtils::quote('foo \\ bar'));
    }

    public function testUnquote()
    {
        $this->assertEquals('foo', HeaderUtils::unquote('foo'));
        $this->assertEquals('az09!#$%&\'*.^_`|~-', HeaderUtils::unquote('az09!#$%&\'*.^_`|~-'));
        $this->assertEquals('foo bar', HeaderUtils::unquote('"foo bar"'));
        $this->assertEquals('foo [bar]', HeaderUtils::unquote('"foo [bar]"'));
        $this->assertEquals('foo "bar"', HeaderUtils::unquote('"foo \"bar\""'));
        $this->assertEquals('foo "bar"', HeaderUtils::unquote('"foo \"\b\a\r\""'));
        $this->assertEquals('foo \\ bar', HeaderUtils::unquote('"foo \\\\ bar"'));
    }
}

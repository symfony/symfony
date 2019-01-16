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
        $this->assertSame(['foo=123', 'bar'], HeaderUtils::split('foo=123,bar', ','));
        $this->assertSame(['foo=123', 'bar'], HeaderUtils::split('foo=123, bar', ','));
        $this->assertSame([['foo=123', 'bar']], HeaderUtils::split('foo=123; bar', ',;'));
        $this->assertSame([['foo=123'], ['bar']], HeaderUtils::split('foo=123, bar', ',;'));
        $this->assertSame(['foo', '123, bar'], HeaderUtils::split('foo=123, bar', '='));
        $this->assertSame(['foo', '123, bar'], HeaderUtils::split(' foo = 123, bar ', '='));
        $this->assertSame([['foo', '123'], ['bar']], HeaderUtils::split('foo=123, bar', ',='));
        $this->assertSame([[['foo', '123']], [['bar'], ['foo', '456']]], HeaderUtils::split('foo=123, bar; foo=456', ',;='));
        $this->assertSame([[['foo', 'a,b;c=d']]], HeaderUtils::split('foo="a,b;c=d"', ',;='));

        $this->assertSame(['foo', 'bar'], HeaderUtils::split('foo,,,, bar', ','));
        $this->assertSame(['foo', 'bar'], HeaderUtils::split(',foo, bar,', ','));
        $this->assertSame(['foo', 'bar'], HeaderUtils::split(' , foo, bar, ', ','));
        $this->assertSame(['foo bar'], HeaderUtils::split('foo "bar"', ','));
        $this->assertSame(['foo bar'], HeaderUtils::split('"foo" bar', ','));
        $this->assertSame(['foo bar'], HeaderUtils::split('"foo" "bar"', ','));

        // These are not a valid header values. We test that they parse anyway,
        // and that both the valid and invalid parts are returned.
        $this->assertSame([], HeaderUtils::split('', ','));
        $this->assertSame([], HeaderUtils::split(',,,', ','));
        $this->assertSame(['foo', 'bar', 'baz'], HeaderUtils::split('foo, "bar", "baz', ','));
        $this->assertSame(['foo', 'bar, baz'], HeaderUtils::split('foo, "bar, baz', ','));
        $this->assertSame(['foo', 'bar, baz\\'], HeaderUtils::split('foo, "bar, baz\\', ','));
        $this->assertSame(['foo', 'bar, baz\\'], HeaderUtils::split('foo, "bar, baz\\\\', ','));
    }

    public function testCombine()
    {
        $this->assertSame(['foo' => '123'], HeaderUtils::combine([['foo', '123']]));
        $this->assertSame(['foo' => true], HeaderUtils::combine([['foo']]));
        $this->assertSame(['foo' => true], HeaderUtils::combine([['Foo']]));
        $this->assertSame(['foo' => '123', 'bar' => true], HeaderUtils::combine([['foo', '123'], ['bar']]));
    }

    public function testToString()
    {
        $this->assertSame('foo', HeaderUtils::toString(['foo' => true], ','));
        $this->assertSame('foo; bar', HeaderUtils::toString(['foo' => true, 'bar' => true], ';'));
        $this->assertSame('foo=123', HeaderUtils::toString(['foo' => '123'], ','));
        $this->assertSame('foo="1 2 3"', HeaderUtils::toString(['foo' => '1 2 3'], ','));
        $this->assertSame('foo="1 2 3", bar', HeaderUtils::toString(['foo' => '1 2 3', 'bar' => true], ','));
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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testMakeDispositionInvalidDisposition()
    {
        HeaderUtils::makeDisposition('invalid', 'foo.html');
    }

    /**
     * @dataProvider provideMakeDisposition
     */
    public function testMakeDisposition($disposition, $filename, $filenameFallback, $expected)
    {
        $this->assertEquals($expected, HeaderUtils::makeDisposition($disposition, $filename, $filenameFallback));
    }

    public function provideMakeDisposition()
    {
        return [
            ['attachment', 'foo.html', 'foo.html', 'attachment; filename=foo.html'],
            ['attachment', 'foo.html', '', 'attachment; filename=foo.html'],
            ['attachment', 'foo bar.html', '', 'attachment; filename="foo bar.html"'],
            ['attachment', 'foo "bar".html', '', 'attachment; filename="foo \\"bar\\".html"'],
            ['attachment', 'foo%20bar.html', 'foo bar.html', 'attachment; filename="foo bar.html"; filename*=utf-8\'\'foo%2520bar.html'],
            ['attachment', 'föö.html', 'foo.html', 'attachment; filename=foo.html; filename*=utf-8\'\'f%C3%B6%C3%B6.html'],
        ];
    }

    /**
     * @dataProvider provideMakeDispositionFail
     * @expectedException \InvalidArgumentException
     */
    public function testMakeDispositionFail($disposition, $filename)
    {
        HeaderUtils::makeDisposition($disposition, $filename);
    }

    public function provideMakeDispositionFail()
    {
        return [
            ['attachment', 'foo%20bar.html'],
            ['attachment', 'foo/bar.html'],
            ['attachment', '/foo.html'],
            ['attachment', 'foo\bar.html'],
            ['attachment', '\foo.html'],
            ['attachment', 'föö.html'],
        ];
    }
}

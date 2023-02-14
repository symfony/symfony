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
    /**
     * @dataProvider provideHeaderToSplit
     */
    public function testSplit(array $expected, string $header, string $separator)
    {
        $this->assertSame($expected, HeaderUtils::split($header, $separator));
    }

    public static function provideHeaderToSplit(): array
    {
        return [
            [['foo=123', 'bar'], 'foo=123,bar', ','],
            [['foo=123', 'bar'], 'foo=123, bar', ','],
            [[['foo=123', 'bar']], 'foo=123; bar', ',;'],
            [[['foo=123'], ['bar']], 'foo=123, bar', ',;'],
            [['foo', '123, bar'], 'foo=123, bar', '='],
            [['foo', '123, bar'], ' foo = 123, bar ', '='],
            [[['foo', '123'], ['bar']], 'foo=123, bar', ',='],
            [[[['foo', '123']], [['bar'], ['foo', '456']]], 'foo=123, bar; foo=456', ',;='],
            [[[['foo', 'a,b;c=d']]], 'foo="a,b;c=d"', ',;='],

            [['foo', 'bar'], 'foo,,,, bar', ','],
            [['foo', 'bar'], ',foo, bar,', ','],
            [['foo', 'bar'], ' , foo, bar, ', ','],
            [['foo bar'], 'foo "bar"', ','],
            [['foo bar'], '"foo" bar', ','],
            [['foo bar'], '"foo" "bar"', ','],

            [[['foo_cookie', 'foo=1&bar=2&baz=3'], ['expires', 'Tue, 22-Sep-2020 06:27:09 GMT'], ['path', '/']], 'foo_cookie=foo=1&bar=2&baz=3; expires=Tue, 22-Sep-2020 06:27:09 GMT; path=/', ';='],
            [[['foo_cookie', 'foo=='], ['expires', 'Tue, 22-Sep-2020 06:27:09 GMT'], ['path', '/']], 'foo_cookie=foo==; expires=Tue, 22-Sep-2020 06:27:09 GMT; path=/', ';='],
            [[['foo_cookie', 'foo=a=b'], ['expires', 'Tue, 22-Sep-2020 06:27:09 GMT'], ['path', '/']], 'foo_cookie=foo="a=b"; expires=Tue, 22-Sep-2020 06:27:09 GMT; path=/', ';='],

            // These are not a valid header values. We test that they parse anyway,
            // and that both the valid and invalid parts are returned.
            [[], '', ','],
            [[], ',,,', ','],
            [['foo', 'bar', 'baz'], 'foo, "bar", "baz', ','],
            [['foo', 'bar, baz'], 'foo, "bar, baz', ','],
            [['foo', 'bar, baz\\'], 'foo, "bar, baz\\', ','],
            [['foo', 'bar, baz\\'], 'foo, "bar, baz\\\\', ','],
        ];
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

    public function testMakeDispositionInvalidDisposition()
    {
        $this->expectException(\InvalidArgumentException::class);
        HeaderUtils::makeDisposition('invalid', 'foo.html');
    }

    /**
     * @dataProvider provideMakeDisposition
     */
    public function testMakeDisposition($disposition, $filename, $filenameFallback, $expected)
    {
        $this->assertEquals($expected, HeaderUtils::makeDisposition($disposition, $filename, $filenameFallback));
    }

    public static function provideMakeDisposition()
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
     */
    public function testMakeDispositionFail($disposition, $filename)
    {
        $this->expectException(\InvalidArgumentException::class);
        HeaderUtils::makeDisposition($disposition, $filename);
    }

    public static function provideMakeDispositionFail()
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

    /**
     * @dataProvider provideParseQuery
     */
    public function testParseQuery(string $query, string $expected = null)
    {
        $this->assertSame($expected ?? $query, http_build_query(HeaderUtils::parseQuery($query), '', '&'));
    }

    public static function provideParseQuery()
    {
        return [
            ['a=b&c=d'],
            ['a.b=c'],
            ['a+b=c'],
            ["a\0b=c", 'a='],
            ['a%00b=c', 'a=c'],
            ['a[b=c', 'a%5Bb=c'],
            ['a]b=c', 'a%5Db=c'],
            ['a[b]=c', 'a%5Bb%5D=c'],
            ['a[b][c.d]=c', 'a%5Bb%5D%5Bc.d%5D=c'],
            ['a%5Bb%5D=c'],
            ['f[%2525][%26][%3D][p.c]=d', 'f%5B%2525%5D%5B%26%5D%5B%3D%5D%5Bp.c%5D=d'],
        ];
    }

    public function testParseCookie()
    {
        $query = 'a.b=c; def%5Bg%5D=h';
        $this->assertSame($query, http_build_query(HeaderUtils::parseQuery($query, false, ';'), '', '; '));
    }

    public function testParseQueryIgnoreBrackets()
    {
        $this->assertSame(['a.b' => ['A', 'B']], HeaderUtils::parseQuery('a.b=A&a.b=B', true));
        $this->assertSame(['a.b[]' => ['A']], HeaderUtils::parseQuery('a.b[]=A', true));
        $this->assertSame(['a.b[]' => ['A']], HeaderUtils::parseQuery('a.b%5B%5D=A', true));
    }
}

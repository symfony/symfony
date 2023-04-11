<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\UriResolver;

class UriResolverTest extends TestCase
{
    /**
     * @dataProvider provideResolverTests
     */
    public function testResolver(string $uri, string $baseUri, string $expected)
    {
        $this->assertEquals($expected, UriResolver::resolve($uri, $baseUri));
    }

    public static function provideResolverTests()
    {
        return [
            ['/foo', 'http://localhost/bar/foo/', 'http://localhost/foo'],
            ['/foo', 'http://localhost/bar/foo', 'http://localhost/foo'],
            ['
            /foo', 'http://localhost/bar/foo/', 'http://localhost/foo'],
            ['/foo
            ', 'http://localhost/bar/foo', 'http://localhost/foo'],

            ['foo', 'http://localhost/bar/foo/', 'http://localhost/bar/foo/foo'],
            ['foo', 'http://localhost/bar/foo', 'http://localhost/bar/foo'],

            ['', 'http://localhost/bar/', 'http://localhost/bar/'],
            ['#', 'http://localhost/bar/', 'http://localhost/bar/#'],
            ['#bar', 'http://localhost/bar?a=b', 'http://localhost/bar?a=b#bar'],
            ['#bar', 'http://localhost/bar/#foo', 'http://localhost/bar/#bar'],
            ['?a=b', 'http://localhost/bar#foo', 'http://localhost/bar?a=b'],
            ['?a=b', 'http://localhost/bar/', 'http://localhost/bar/?a=b'],

            ['http://login.foo.com/foo', 'http://localhost/bar/', 'http://login.foo.com/foo'],
            ['https://login.foo.com/foo', 'https://localhost/bar/', 'https://login.foo.com/foo'],
            ['mailto:foo@bar.com', 'http://localhost/foo', 'mailto:foo@bar.com'],

            // tests schema relative URL (issue #7169)
            ['//login.foo.com/foo', 'http://localhost/bar/', 'http://login.foo.com/foo'],
            ['//login.foo.com/foo', 'https://localhost/bar/', 'https://login.foo.com/foo'],

            ['?foo=2', 'http://localhost?foo=1', 'http://localhost?foo=2'],
            ['?foo=2', 'http://localhost/?foo=1', 'http://localhost/?foo=2'],
            ['?foo=2', 'http://localhost/bar?foo=1', 'http://localhost/bar?foo=2'],
            ['?foo=2', 'http://localhost/bar/?foo=1', 'http://localhost/bar/?foo=2'],
            ['?bar=2', 'http://localhost?foo=1', 'http://localhost?bar=2'],

            ['foo', 'http://login.foo.com/bar/baz?/query/string', 'http://login.foo.com/bar/foo'],

            ['.', 'http://localhost/foo/bar/baz', 'http://localhost/foo/bar/'],
            ['./', 'http://localhost/foo/bar/baz', 'http://localhost/foo/bar/'],
            ['./foo', 'http://localhost/foo/bar/baz', 'http://localhost/foo/bar/foo'],
            ['..', 'http://localhost/foo/bar/baz', 'http://localhost/foo/'],
            ['../', 'http://localhost/foo/bar/baz', 'http://localhost/foo/'],
            ['../foo', 'http://localhost/foo/bar/baz', 'http://localhost/foo/foo'],
            ['../..', 'http://localhost/foo/bar/baz', 'http://localhost/'],
            ['../../', 'http://localhost/foo/bar/baz', 'http://localhost/'],
            ['../../foo', 'http://localhost/foo/bar/baz', 'http://localhost/foo'],
            ['../../foo', 'http://localhost/bar/foo/', 'http://localhost/foo'],
            ['../bar/../../foo', 'http://localhost/bar/foo/', 'http://localhost/foo'],
            ['../bar/./../../foo', 'http://localhost/bar/foo/', 'http://localhost/foo'],
            ['../../', 'http://localhost/', 'http://localhost/'],
            ['../../', 'http://localhost', 'http://localhost/'],

            ['/foo', 'http://localhost?bar=1', 'http://localhost/foo'],
            ['/foo', 'http://localhost#bar', 'http://localhost/foo'],
            ['/foo', 'file:///', 'file:///foo'],
            ['/foo', 'file:///bar/baz', 'file:///foo'],
            ['foo', 'file:///', 'file:///foo'],
            ['foo', 'file:///bar/baz', 'file:///bar/foo'],

            ['foo', 'http://localhost?bar=1', 'http://localhost/foo'],
            ['foo', 'http://localhost#bar', 'http://localhost/foo'],
        ];
    }
}

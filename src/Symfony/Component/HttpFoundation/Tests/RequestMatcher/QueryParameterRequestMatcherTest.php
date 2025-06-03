<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\RequestMatcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\QueryParameterRequestMatcher;

class QueryParameterRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getDataForArray
     */
    public function testArray(string $uri, bool $matches)
    {
        $matcher = new QueryParameterRequestMatcher(['foo', 'bar']);
        $request = Request::create($uri);
        $this->assertSame($matches, $matcher->matches($request));
    }

    /**
     * @dataProvider getDataForArray
     */
    public function testCommaSeparatedString(string $uri, bool $matches)
    {
        $matcher = new QueryParameterRequestMatcher('foo, bar');
        $request = Request::create($uri);
        $this->assertSame($matches, $matcher->matches($request));
    }

    /**
     * @dataProvider getDataForSingleString
     */
    public function testSingleString(string $uri, bool $matches)
    {
        $matcher = new QueryParameterRequestMatcher('foo');
        $request = Request::create($uri);
        $this->assertSame($matches, $matcher->matches($request));
    }

    public static function getDataForArray(): \Generator
    {
        yield ['https://example.com?foo=&bar=', true];
        yield ['https://example.com?foo=foo1&bar=bar1', true];
        yield ['https://example.com?foo=foo1&bar=bar1&baz=baz1', true];
        yield ['https://example.com?foo=', false];
        yield ['https://example.com', false];
    }

    public static function getDataForSingleString(): \Generator
    {
        yield ['https://example.com?foo=&bar=', true];
        yield ['https://example.com?foo=foo1', true];
        yield ['https://example.com?foo=', true];
        yield ['https://example.com?bar=bar1&baz=baz1', false];
        yield ['https://example.com', false];
    }
}

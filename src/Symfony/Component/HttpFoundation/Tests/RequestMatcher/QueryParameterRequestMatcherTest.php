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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\QueryParameterRequestMatcher;

class QueryParameterRequestMatcherTest extends TestCase
{
    #[DataProvider('getDataForArray')]
    public function testArray(string $uri, bool $matches)
    {
        $matcher = new QueryParameterRequestMatcher(['foo', 'bar']);
        $request = Request::create($uri);
        $this->assertSame($matches, $matcher->matches($request));
    }

    #[DataProvider('getDataForArray')]
    public function testCommaSeparatedString(string $uri, bool $matches)
    {
        $matcher = new QueryParameterRequestMatcher('foo, bar');
        $request = Request::create($uri);
        $this->assertSame($matches, $matcher->matches($request));
    }

    #[DataProvider('getDataForSingleString')]
    public function testSingleString(string $uri, bool $matches)
    {
        $matcher = new QueryParameterRequestMatcher('foo');
        $request = Request::create($uri);
        $this->assertSame($matches, $matcher->matches($request));
    }

    #[DataProvider('getDataForParameterNames')]
    public function testParameterNames(array|string $parameters, string $uri, bool $matches)
    {
        $matcher = new QueryParameterRequestMatcher($parameters);

        $this->assertSame($matches, $matcher->matches(Request::create($uri)));
    }

    public static function getDataForParameterNames(): \Generator
    {
        yield ['authorizationCode', 'https://example.com?authorizationCode=abc', true];
        yield ['authorizationCode', 'https://example.com?authorizationcode=abc', false];
        yield ['foo', 'https://example.com?Foo=1', false];
        yield [['foo', 'foo'], 'https://example.com?foo=1', true];
        yield ['0', 'https://example.com?extra=1&0=2', true];
        yield [[], 'https://example.com?foo=1', true];
    }

    public static function getDataForArray(): \Generator
    {
        yield ['https://example.com?foo=&bar=', true];
        yield ['https://example.com?foo=foo1&bar=bar1', true];
        yield ['https://example.com?foo=foo1&bar=bar1&baz=baz1', true];
        yield ['https://example.com?bar=bar1&foo=foo1', true];
        yield ['https://example.com?baz=baz1&foo=foo1&bar=bar1', true];
        yield ['https://example.com?foo=foo1&baz=baz1&bar=bar1', true];
        yield ['https://example.com?foo=', false];
        yield ['https://example.com', false];
    }

    public static function getDataForSingleString(): \Generator
    {
        yield ['https://example.com?foo=&bar=', true];
        yield ['https://example.com?foo=foo1', true];
        yield ['https://example.com?bar=bar1&foo=foo1', true];
        yield ['https://example.com?foo=', true];
        yield ['https://example.com?bar=bar1&baz=baz1', false];
        yield ['https://example.com', false];
    }
}

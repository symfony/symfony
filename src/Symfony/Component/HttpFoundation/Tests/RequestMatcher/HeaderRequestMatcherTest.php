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
use Symfony\Component\HttpFoundation\RequestMatcher\HeaderRequestMatcher;

class HeaderRequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getDataForArray
     */
    public function testArray(array $headers, bool $matches)
    {
        $matcher = new HeaderRequestMatcher(['x-foo', 'bar']);

        $request = Request::create('https://example.com');
        foreach ($headers as $k => $v) {
            $request->headers->set($k, $v);
        }

        $this->assertSame($matches, $matcher->matches($request));
    }

    /**
     * @dataProvider getDataForArray
     */
    public function testCommaSeparatedString(array $headers, bool $matches)
    {
        $matcher = new HeaderRequestMatcher('x-foo, bar');

        $request = Request::create('https://example.com');
        foreach ($headers as $k => $v) {
            $request->headers->set($k, $v);
        }

        $this->assertSame($matches, $matcher->matches($request));
    }

    /**
     * @dataProvider getDataForSingleString
     */
    public function testSingleString(array $headers, bool $matches)
    {
        $matcher = new HeaderRequestMatcher('x-foo');

        $request = Request::create('https://example.com');
        foreach ($headers as $k => $v) {
            $request->headers->set($k, $v);
        }

        $this->assertSame($matches, $matcher->matches($request));
    }

    public static function getDataForArray(): \Generator
    {
        yield 'Superfluous header' => [['X-Foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'], true];
        yield 'Exact match' => [['X-Foo' => 'foo', 'bar' => 'bar'], true];
        yield 'Case insensitivity' => [['x-foo' => 'foo', 'BAR' => 'bar'], true];
        yield 'Only one header matching' => [['bar' => 'bar', 'baz' => 'baz'], false];
        yield 'Only one header' => [['X-foo' => 'foo'], false];
        yield 'Header name as a value' => [['X-foo'], false];
        yield 'Empty headers' => [[], false];
    }

    public static function getDataForSingleString(): \Generator
    {
        yield 'Superfluous header' => [['X-Foo' => 'foo', 'bar' => 'bar'], true];
        yield 'Exact match' => [['X-foo' => 'foo'], true];
        yield 'Case insensitivity' => [['x-foo' => 'foo'], true];
        yield 'Header name as a value' => [['X-foo'], false];
        yield 'Empty headers' => [[], false];
    }
}

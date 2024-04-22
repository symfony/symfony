<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uri\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uri\QueryString;

/**
 * @covers \Symfony\Component\Uri\QueryString
 */
class QueryStringTest extends TestCase
{
    public function testBasicString()
    {
        $queryString = QueryString::parse('foo=1&bar=2&baz=3');

        $this->assertSame('1', $queryString->get('foo'));
        $this->assertSame('2', $queryString->get('bar'));
        $this->assertSame('3', $queryString->get('baz'));
    }

    public function testQueryStringWithDotAndUnderscore()
    {
        $queryString = QueryString::parse('foo.bar=1&foo_bar=2');

        $this->assertSame('1', $queryString->get('foo.bar'));
        $this->assertSame('2', $queryString->get('foo_bar'));
    }

    public function testQueryStringWithPlus()
    {
        $queryString = QueryString::parse('foo+bar=1');

        $this->assertSame('1', $queryString->get('foo bar'));
    }

    public function testQueryStringWithArray()
    {
        $queryString = QueryString::parse('foo=1&foo=2&bar=3');

        $this->assertSame('1', $queryString->get('foo'));
        $this->assertSame(['1', '2'], $queryString->getAll('foo'));
        $this->assertSame('3', $queryString->get('bar'));
    }

    public function testQueryStringWithNestedArrays()
    {
        $queryString = QueryString::parse('foo[bar]=1&foo[baz][qux]=2');

        $this->assertSame('1', $queryString->get('foo')['bar']);
        $this->assertSame('2', $queryString->get('foo')['baz']['qux']);
    }

    public function testEmptyParameter()
    {
        $queryString = QueryString::parse('foo=1&bar=&baz=3');

        $this->assertSame('1', $queryString->get('foo'));
        $this->assertSame('', $queryString->get('bar'));
        $this->assertSame('3', $queryString->get('baz'));
    }

    public function testEmptyQueryString()
    {
        $queryString = QueryString::parse('');

        $this->assertEmpty($queryString->all());
    }

    public function testMultiEqualSignInParameter()
    {
        $queryString = QueryString::parse('foo=1=2&bar=3&baz=4');

        $this->assertSame('1=2', $queryString->get('foo'));
        $this->assertSame('3', $queryString->get('bar'));
        $this->assertSame('4', $queryString->get('baz'));
    }

    public function testSetParameter()
    {
        $queryString = QueryString::parse('foo=1&bar=2&baz=3');
        $queryString->set('bar', 4);

        $this->assertSame('1', $queryString->get('foo'));
        $this->assertSame('4', $queryString->get('bar'));
        $this->assertSame('3', $queryString->get('baz'));
    }

    public function testGetUnknownParameter()
    {
        $queryString = QueryString::parse('foo=1&bar=2&baz=3');

        $this->assertNull($queryString->get('unknown'));
    }

    public function testToString()
    {
        $queryString = QueryString::parse('foo=1&bar=2&baz=3');

        $this->assertSame('foo=1&bar=2&baz=3', (string) $queryString);
    }

    public function testToStringWithArray()
    {
        $queryString = QueryString::parse('foo=1&foo=2&bar=3');

        $this->assertSame('foo[0]=1&foo[1]=2&bar=3', (string) $queryString);
    }

    public function testToStringWithNestedArray()
    {
        $queryString = QueryString::parse('foo[bar][0]=1&foo[bar][1]=2&foo[bar][5]=2');

        $this->assertSame('foo[bar][0]=1&foo[bar][1]=2&foo[bar][5]=2', (string) $queryString);
    }

    public function testToStringWithNestedArrayWithoutIndex()
    {
        $queryString = QueryString::parse('foo[bar]=1&foo[bar]=2');

        $this->assertSame('foo[bar][0]=1&foo[bar][1]=2', (string) $queryString);
    }

    public function testToStringWithSpaces()
    {
        $queryString = QueryString::parse('foo=1&bar=2&baz=3&foo bar=4');

        $this->assertSame('foo=1&bar=2&baz=3&foo+bar=4', (string) $queryString);
    }

    public function testToStringWithDotAndUnderscores()
    {
        $queryString = QueryString::parse('foo.bar=1&foo_bar=2');

        $this->assertSame('foo.bar=1&foo_bar=2', (string) $queryString);
    }
}

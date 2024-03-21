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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DomCrawler\Link;

class LinkTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructorWithANonATag()
    {
        $this->expectException(\LogicException::class);
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><div><div></html>');

        new Link($dom->getElementsByTagName('div')->item(0), 'http://www.example.com/');
    }

    /**
     * @requires PHP 8.4
     */
    public function testConstructorWithANonATagFromHTMLDocument()
    {
        $this->expectException(\LogicException::class);
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><div><div></html>');

        new Link($dom->getElementsByTagName('div')->item(0), 'http://www.example.com/');
    }

    public function testBaseUriIsOptionalWhenLinkUrlIsAbsolute()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><a href="https://example.com/foo">foo</a></html>');

        $link = new Link($dom->getElementsByTagName('a')->item(0));
        $this->assertSame('https://example.com/foo', $link->getUri());
    }

    /**
     * @requires PHP 8.4
     */
    public function testBaseUriIsOptionalWhenLinkUrlIsAbsoluteFromHTMLDocument()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><a href="https://example.com/foo">foo</a></html>');

        $link = new Link($dom->getElementsByTagName('a')->item(0));
        $this->assertSame('https://example.com/foo', $link->getUri());
    }

    public function testAbsoluteBaseUriIsMandatoryWhenLinkUrlIsRelative()
    {
        $this->expectException(\InvalidArgumentException::class);
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><a href="/foo">foo</a></html>');

        $link = new Link($dom->getElementsByTagName('a')->item(0), 'example.com');
        $link->getUri();
    }

    /**
     * @requires PHP 8.4
     */
    public function testAbsoluteBaseUriIsMandatoryWhenLinkUrlIsRelativeFromHTMLDocument()
    {
        $this->expectException(\InvalidArgumentException::class);
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><a href="/foo">foo</a></html>');

        $link = new Link($dom->getElementsByTagName('a')->item(0), 'example.com');
        $link->getUri();
    }

    /**
     * @group legacy
     */
    public function testGetNodeIsDeprecated()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><a href="/foo">foo</a></html>');

        $node = $dom->getElementsByTagName('a')->item(0);
        $link = new Link($node, 'http://example.com/');

        $this->expectDeprecation('Since symfony/dom-crawler 7.1: The "Symfony\Component\DomCrawler\AbstractUriElement::getNode()" method is deprecated, use "Symfony\Component\DomCrawler\AbstractUriElement::getDomNode()" instead.');
        $link->getNode();
    }

    public function testGetDomNode()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><a href="/foo">foo</a></html>');

        $node = $dom->getElementsByTagName('a')->item(0);
        $link = new Link($node, 'http://example.com/');

        $this->assertEquals($node, $link->getDomNode(), '->getDomNode() returns the node associated with the link');
    }

    /**
     * @requires PHP 8.4
     */
    public function testGetNodeFromHTMLDocument()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><a href="/foo">foo</a></html>');

        $node = $dom->getElementsByTagName('a')->item(0);
        $link = new Link($node, 'http://example.com/');

        $this->assertEquals($node, $link->getDomNode(), '->getDomNode() returns the node associated with the link');
    }

    /**
     * @requires PHP 8.4
     *
     * @group legacy
     */
    public function testGetNodeOnModernNode()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><a href="/foo">foo</a></html>');

        $node = $dom->getElementsByTagName('a')->item(0);
        $link = new Link($node, 'http://example.com/');

        $this->expectDeprecation('Since symfony/dom-crawler 7.1: The "Symfony\Component\DomCrawler\AbstractUriElement::getNode()" method is deprecated, use "Symfony\Component\DomCrawler\AbstractUriElement::getDomNode()" instead.');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The node is not an instance of legacy \DOMElement. Use "getDomNode()" instead.');
        $link->getNode();
    }

    public function testGetMethod()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><a href="/foo">foo</a></html>');

        $node = $dom->getElementsByTagName('a')->item(0);
        $link = new Link($node, 'http://example.com/');

        $this->assertEquals('GET', $link->getMethod(), '->getMethod() returns the method of the link');

        $link = new Link($node, 'http://example.com/', 'post');
        $this->assertEquals('POST', $link->getMethod(), '->getMethod() returns the method of the link');
    }

    /**
     * @requires PHP 8.4
     */
    public function testGetMethodFromHTMLDocument()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><a href="/foo">foo</a></html>');

        $node = $dom->getElementsByTagName('a')->item(0);
        $link = new Link($node, 'http://example.com/');

        $this->assertEquals('GET', $link->getMethod(), '->getMethod() returns the method of the link');

        $link = new Link($node, 'http://example.com/', 'post');
        $this->assertEquals('POST', $link->getMethod(), '->getMethod() returns the method of the link');
    }

    /**
     * @dataProvider getGetUriTests
     */
    public function testGetUri($url, $currentUri, $expected)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML(\sprintf('<html><a href="%s">foo</a></html>', $url));
        $link = new Link($dom->getElementsByTagName('a')->item(0), $currentUri);

        $this->assertEquals($expected, $link->getUri());
    }

    /**
     * @requires PHP 8.4
     *
     * @dataProvider getGetUriTests
     */
    public function testGetUriFromHTMLDocument($url, $currentUri, $expected)
    {
        $dom = \DOM\HTMLDocument::createFromString(sprintf('<!DOCTYPE html><html><a href="%s">foo</a></html>', $url));

        $link = new Link($dom->getElementsByTagName('a')->item(0), $currentUri);

        $this->assertEquals($expected, $link->getUri());
    }

    /**
     * @dataProvider getGetUriTests
     */
    public function testGetUriOnArea($url, $currentUri, $expected)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML(\sprintf('<html><map><area href="%s" /></map></html>', $url));
        $link = new Link($dom->getElementsByTagName('area')->item(0), $currentUri);

        $this->assertEquals($expected, $link->getUri());
    }

    /**
     * @requires PHP 8.4
     *
     * @dataProvider getGetUriTests
     */
    public function testGetUriOnAreaFromHTMLDocument($url, $currentUri, $expected)
    {
        $dom = \DOM\HTMLDocument::createFromString(sprintf('<!DOCTYPE html><html><map><area href="%s"></map></html>', $url));

        $link = new Link($dom->getElementsByTagName('area')->item(0), $currentUri);

        $this->assertEquals($expected, $link->getUri());
    }

    /**
     * @dataProvider getGetUriTests
     */
    public function testGetUriOnLink($url, $currentUri, $expected)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML(\sprintf('<html><head><link href="%s" /></head></html>', $url));
        $link = new Link($dom->getElementsByTagName('link')->item(0), $currentUri);

        $this->assertEquals($expected, $link->getUri());
    }

    /**
     * @requires PHP 8.4
     *
     * @dataProvider getGetUriTests
     */
    public function testGetUriOnLinkFromHTMLDocument($url, $currentUri, $expected)
    {
        $dom = \DOM\HTMLDocument::createFromString(sprintf('<!DOCTYPE html><html><head><link href="%s"></head></html>', $url));

        $link = new Link($dom->getElementsByTagName('link')->item(0), $currentUri);

        $this->assertEquals($expected, $link->getUri());
    }

    public static function getGetUriTests()
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
        ];
    }
}

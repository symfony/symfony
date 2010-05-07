<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DomCrawler;

use Symfony\Components\DomCrawler\Link;

class LinkTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><div><div></html>');

        $node = $dom->getElementsByTagName('div')->item(0);

        try {
            new Link($node);
            $this->fail('__construct() throws a \LogicException if the node is not an "a" tag');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\LogicException', $e, '__construct() throws a \LogicException if the node is not an "a" tag');
        }
    }

    public function testGetters()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><a href="/foo">foo</a></html>');

        $node = $dom->getElementsByTagName('a')->item(0);
        $link = new Link($node);

        $this->assertEquals('/foo', $link->getUri(), '->getUri() returns the URI of the link');
        $this->assertEquals($node, $link->getNode(), '->getNode() returns the node associated with the link');
        $this->assertEquals('get', $link->getMethod(), '->getMethod() returns the method of the link');

        $link = new Link($node, 'post');
        $this->assertEquals('post', $link->getMethod(), '->getMethod() returns the method of the link');

        $link = new Link($node, 'get', 'http://localhost', '/bar/');
        $this->assertEquals('http://localhost/foo', $link->getUri(), '->getUri() returns the absolute URI of the link');
        $this->assertEquals('/foo', $link->getUri(false), '->getUri() returns the relative URI of the link if false is the first argument');

        $dom = new \DOMDocument();
        $dom->loadHTML('<html><a href="foo">foo</a></html>');
        $node = $dom->getElementsByTagName('a')->item(0);

        $link = new Link($node, 'get', 'http://localhost', '/bar/');
        $this->assertEquals('http://localhost/bar/foo', $link->getUri(), '->getUri() returns the absolute URI of the link for relative hrefs');
        $this->assertEquals('/bar/foo', $link->getUri(false), '->getUri() returns the relative URI of the link if false is the first argument');
    }
}

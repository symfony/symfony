<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\BrowserKit;

use Symfony\Components\BrowserKit\Response;

class ResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testGetUri()
    {
        $response = new Response('foo');
        $this->assertEquals('foo', $response->getContent(), '->getContent() returns the content of the response');
    }

    public function testGetStatus()
    {
        $response = new Response('foo', 304);
        $this->assertEquals('304', $response->getStatus(), '->getStatus() returns the status of the response');
    }

    public function testGetHeaders()
    {
        $response = new Response('foo', 304, array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $response->getHeaders(), '->getHeaders() returns the headers of the response');
    }

    public function testGetHeader()
    {
        $response = new Response('foo', 304, array('Content-Type' => 'text/html'));

        $this->assertEquals('text/html', $response->getHeader('Content-Type'), '->getHeader() returns a header of the response');
        $this->assertEquals('text/html', $response->getHeader('content-type'), '->getHeader() returns a header of the response');
        $this->assertEquals('text/html', $response->getHeader('content_type'), '->getHeader() returns a header of the response');

        $this->assertNull($response->getHeader('foo'), '->getHeader() returns null if the header is not defined');
    }

    public function testGetCookies()
    {
        $response = new Response('foo', 304, array(), array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $response->getCookies(), '->getCookies() returns the cookies of the response');
    }

    public function testMagicToString()
    {
        $response = new Response('foo', 304, array('foo' => 'bar'), array('foo' => array('value' => 'bar')));

        $this->assertEquals("foo: bar\nSet-Cookie: foo=bar\n\nfoo", $response->__toString(), '->__toString() returns the headers and the content as a string');
    }
}

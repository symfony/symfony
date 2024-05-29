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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamedResponseTest extends TestCase
{
    public function testConstructor()
    {
        $response = new StreamedResponse(function () { echo 'foo'; }, 404, ['Content-Type' => 'text/plain']);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->headers->get('Content-Type'));
    }

    public function testPrepareWith11Protocol()
    {
        $response = new StreamedResponse(function () { echo 'foo'; });
        $request = Request::create('/');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.1');

        $response->prepare($request);

        $this->assertEquals('1.1', $response->getProtocolVersion());
        $this->assertNotEquals('chunked', $response->headers->get('Transfer-Encoding'), 'Apache assumes responses with a Transfer-Encoding header set to chunked to already be encoded.');
    }

    public function testPrepareWith10Protocol()
    {
        $response = new StreamedResponse(function () { echo 'foo'; });
        $request = Request::create('/');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.0');

        $response->prepare($request);

        $this->assertEquals('1.0', $response->getProtocolVersion());
        $this->assertNull($response->headers->get('Transfer-Encoding'));
    }

    public function testPrepareWithHeadRequest()
    {
        $response = new StreamedResponse(function () { echo 'foo'; }, 200, ['Content-Length' => '123']);
        $request = Request::create('/', 'HEAD');

        $response->prepare($request);

        $this->assertSame('123', $response->headers->get('Content-Length'));
    }

    public function testPrepareWithCacheHeaders()
    {
        $response = new StreamedResponse(function () { echo 'foo'; }, 200, ['Cache-Control' => 'max-age=600, public']);
        $request = Request::create('/', 'GET');

        $response->prepare($request);
        $this->assertEquals('max-age=600, public', $response->headers->get('Cache-Control'));
    }

    public function testSendContent()
    {
        $called = 0;

        $response = new StreamedResponse(function () use (&$called) { ++$called; });

        $response->sendContent();
        $this->assertEquals(1, $called);

        $response->sendContent();
        $this->assertEquals(1, $called);
    }

    public function testSendContentWithNonCallable()
    {
        $this->expectException(\LogicException::class);
        $response = new StreamedResponse(null);
        $response->sendContent();
    }

    public function testSetContent()
    {
        $this->expectException(\LogicException::class);
        $response = new StreamedResponse(function () { echo 'foo'; });
        $response->setContent('foo');
    }

    public function testGetContent()
    {
        $response = new StreamedResponse(function () { echo 'foo'; });
        $this->assertFalse($response->getContent());
    }

    public function testReturnThis()
    {
        $response = new StreamedResponse(function () {});
        $this->assertInstanceOf(StreamedResponse::class, $response->sendContent());
        $this->assertInstanceOf(StreamedResponse::class, $response->sendContent());

        $response = new StreamedResponse(function () {});
        $this->assertInstanceOf(StreamedResponse::class, $response->sendHeaders());
        $this->assertInstanceOf(StreamedResponse::class, $response->sendHeaders());
    }

    public function testSetNotModified()
    {
        $response = new StreamedResponse(function () { echo 'foo'; });
        $modified = $response->setNotModified();
        $this->assertSame($response, $modified);
        $this->assertEquals(304, $modified->getStatusCode());

        ob_start();
        $modified->sendContent();
        $string = ob_get_clean();
        $this->assertEmpty($string);
    }

    public function testSendInformationalResponse()
    {
        $response = new StreamedResponse();
        $response->sendHeaders(103);

        // Informational responses must not override the main status code
        $this->assertSame(200, $response->getStatusCode());

        $response->sendHeaders();
    }
}

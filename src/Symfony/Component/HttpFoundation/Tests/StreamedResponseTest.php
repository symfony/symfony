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

        self::assertEquals(404, $response->getStatusCode());
        self::assertEquals('text/plain', $response->headers->get('Content-Type'));
    }

    public function testPrepareWith11Protocol()
    {
        $response = new StreamedResponse(function () { echo 'foo'; });
        $request = Request::create('/');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.1');

        $response->prepare($request);

        self::assertEquals('1.1', $response->getProtocolVersion());
        self::assertNotEquals('chunked', $response->headers->get('Transfer-Encoding'), 'Apache assumes responses with a Transfer-Encoding header set to chunked to already be encoded.');
    }

    public function testPrepareWith10Protocol()
    {
        $response = new StreamedResponse(function () { echo 'foo'; });
        $request = Request::create('/');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.0');

        $response->prepare($request);

        self::assertEquals('1.0', $response->getProtocolVersion());
        self::assertNull($response->headers->get('Transfer-Encoding'));
    }

    public function testPrepareWithHeadRequest()
    {
        $response = new StreamedResponse(function () { echo 'foo'; }, 200, ['Content-Length' => '123']);
        $request = Request::create('/', 'HEAD');

        $response->prepare($request);

        self::assertSame('123', $response->headers->get('Content-Length'));
    }

    public function testPrepareWithCacheHeaders()
    {
        $response = new StreamedResponse(function () { echo 'foo'; }, 200, ['Cache-Control' => 'max-age=600, public']);
        $request = Request::create('/', 'GET');

        $response->prepare($request);
        self::assertEquals('max-age=600, public', $response->headers->get('Cache-Control'));
    }

    public function testSendContent()
    {
        $called = 0;

        $response = new StreamedResponse(function () use (&$called) { ++$called; });

        $response->sendContent();
        self::assertEquals(1, $called);

        $response->sendContent();
        self::assertEquals(1, $called);
    }

    public function testSendContentWithNonCallable()
    {
        self::expectException(\LogicException::class);
        $response = new StreamedResponse(null);
        $response->sendContent();
    }

    public function testSetContent()
    {
        self::expectException(\LogicException::class);
        $response = new StreamedResponse(function () { echo 'foo'; });
        $response->setContent('foo');
    }

    public function testGetContent()
    {
        $response = new StreamedResponse(function () { echo 'foo'; });
        self::assertFalse($response->getContent());
    }

    /**
     * @group legacy
     */
    public function testCreate()
    {
        $response = StreamedResponse::create(function () {}, 204);

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertEquals(204, $response->getStatusCode());
    }

    public function testReturnThis()
    {
        $response = new StreamedResponse(function () {});
        self::assertInstanceOf(StreamedResponse::class, $response->sendContent());
        self::assertInstanceOf(StreamedResponse::class, $response->sendContent());

        $response = new StreamedResponse(function () {});
        self::assertInstanceOf(StreamedResponse::class, $response->sendHeaders());
        self::assertInstanceOf(StreamedResponse::class, $response->sendHeaders());
    }

    public function testSetNotModified()
    {
        $response = new StreamedResponse(function () { echo 'foo'; });
        $modified = $response->setNotModified();
        self::assertObjectHasAttribute('headers', $modified);
        self::assertObjectHasAttribute('content', $modified);
        self::assertObjectHasAttribute('version', $modified);
        self::assertObjectHasAttribute('statusCode', $modified);
        self::assertObjectHasAttribute('statusText', $modified);
        self::assertObjectHasAttribute('charset', $modified);
        self::assertEquals(304, $modified->getStatusCode());

        ob_start();
        $modified->sendContent();
        $string = ob_get_clean();
        self::assertEmpty($string);
    }
}

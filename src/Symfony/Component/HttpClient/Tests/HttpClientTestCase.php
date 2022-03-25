<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase as BaseHttpClientTestCase;

abstract class HttpClientTestCase extends BaseHttpClientTestCase
{
    public function testTimeoutOnDestruct()
    {
        if (!method_exists(parent::class, 'testTimeoutOnDestruct')) {
            $this->markTestSkipped('BaseHttpClientTestCase doesn\'t have testTimeoutOnDestruct().');
        }

        parent::testTimeoutOnDestruct();
    }

    public function testAcceptHeader()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057');
        $requestHeaders = $response->toArray();

        $this->assertSame('*/*', $requestHeaders['HTTP_ACCEPT']);

        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => [
                'Accept' => 'foo/bar',
            ],
        ]);
        $requestHeaders = $response->toArray();

        $this->assertSame('foo/bar', $requestHeaders['HTTP_ACCEPT']);

        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => [
                'Accept' => null,
            ],
        ]);
        $requestHeaders = $response->toArray();

        $this->assertArrayNotHasKey('HTTP_ACCEPT', $requestHeaders);
    }

    public function testToStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');
        $stream = $response->toStream();

        $this->assertSame("{\n    \"SER", fread($stream, 10));
        $this->assertSame('VER_PROTOCOL', fread($stream, 12));
        $this->assertFalse(feof($stream));
        $this->assertTrue(rewind($stream));

        $this->assertIsArray(json_decode(fread($stream, 1024), true));
        $this->assertSame('', fread($stream, 1));
        $this->assertTrue(feof($stream));
    }

    public function testStreamCopyToStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');
        $h = fopen('php://temp', 'w+');
        stream_copy_to_stream($response->toStream(), $h);

        $this->assertTrue(rewind($h));
        $this->assertSame("{\n    \"SER", fread($h, 10));
        $this->assertSame('VER_PROTOCOL', fread($h, 12));
        $this->assertFalse(feof($h));
    }

    public function testToStream404()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/404');
        $stream = $response->toStream(false);

        $this->assertSame("{\n    \"SER", fread($stream, 10));
        $this->assertSame('VER_PROTOCOL', fread($stream, 12));
        $this->assertSame($response, stream_get_meta_data($stream)['wrapper_data']->getResponse());
        $this->assertSame(404, $response->getStatusCode());

        $response = $client->request('GET', 'http://localhost:8057/404');
        $this->expectException(ClientException::class);
        $response->toStream();
    }

    public function testNonBlockingStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');
        $stream = $response->toStream();
        usleep(10000);

        $this->assertTrue(stream_set_blocking($stream, false));
        $this->assertSame('<1>', fread($stream, 8192));
        $this->assertFalse(feof($stream));

        $this->assertTrue(stream_set_blocking($stream, true));
        $this->assertSame('<2>', fread($stream, 8192));
        $this->assertSame('', fread($stream, 8192));
        $this->assertTrue(feof($stream));
    }

    public function testTimeoutIsNotAFatalError()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body', [
            'timeout' => 0.25,
        ]);
        $this->assertSame(200, $response->getStatusCode());

        try {
            $response->getContent();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
        }

        for ($i = 0; $i < 10; ++$i) {
            try {
                $this->assertSame('<1><2>', $response->getContent());
                break;
            } catch (TransportException $e) {
            }
        }

        if (10 === $i) {
            throw $e;
        }
    }

    public function testHandleIsRemovedOnException()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        try {
            $client->request('GET', 'http://localhost:8057/304');
            $this->fail(RedirectionExceptionInterface::class.' expected');
        } catch (RedirectionExceptionInterface $e) {
            // The response content-type mustn't be json as that calls getContent
            // @see src/Symfony/Component/HttpClient/Exception/HttpExceptionTrait.php:58
            $this->assertStringNotContainsString('json', $e->getResponse()->getHeaders(false)['content-type'][0] ?? '');
            unset($e);

            $r = new \ReflectionProperty($client, 'multi');
            $r->setAccessible(true);
            /** @var ClientState $clientState */
            $clientState = $r->getValue($client);

            $this->assertCount(0, $clientState->handlesActivity);
            $this->assertCount(0, $clientState->openHandles);
        }
    }

    public function testDebugInfoOnDestruct()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $traceInfo = [];
        $client->request('GET', 'http://localhost:8057', ['on_progress' => function (int $dlNow, int $dlSize, array $info) use (&$traceInfo) {
            $traceInfo = $info;
        }]);

        $this->assertNotEmpty($traceInfo['debug']);
    }

    public function testFixContentLength()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/post', [
            'body' => 'abc=def',
            'headers' => ['Content-Length: 4'],
        ]);

        $body = $response->toArray();

        $this->assertSame(['abc' => 'def', 'REQUEST_METHOD' => 'POST'], $body);
    }

    public function testNegativeTimeout()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->assertSame(200, $client->request('GET', 'http://localhost:8057', [
            'timeout' => -1,
        ])->getStatusCode());
    }

    public function testRedirectAfterPost()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/302/relative', [
            'body' => '',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString("\r\nContent-Length: 0", $response->getInfo('debug'));
    }

    public function testEmptyPut()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('PUT', 'http://localhost:8057/post', [
            'headers' => ['Content-Length' => '0'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString("\r\nContent-Length: ", $response->getInfo('debug'));
    }

    public function testNullBody()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $client->request('POST', 'http://localhost:8057/post', [
            'body' => null,
        ]);

        $this->expectNotToPerformAssertions();
    }
}

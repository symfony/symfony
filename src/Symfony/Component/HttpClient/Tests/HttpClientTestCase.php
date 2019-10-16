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
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase as BaseHttpClientTestCase;

abstract class HttpClientTestCase extends BaseHttpClientTestCase
{
    public function testMaxDuration()
    {
        $this->markTestSkipped('Implemented as of version 4.4');
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

    public function testToStream404()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/404');
        $stream = $response->toStream(false);

        $this->assertSame("{\n    \"SER", fread($stream, 10));
        $this->assertSame('VER_PROTOCOL', fread($stream, 12));
        $this->assertSame($response, stream_get_meta_data($stream)['wrapper_data']->getResponse());
        $this->assertSame(404, $response->getStatusCode());

        $this->expectException(ClientException::class);
        $response = $client->request('GET', 'http://localhost:8057/404');
        $stream = $response->toStream();
    }

    public function testConditionalBuffering()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');
        $firstContent = $response->getContent();
        $secondContent = $response->getContent();

        $this->assertSame($firstContent, $secondContent);

        $response = $client->request('GET', 'http://localhost:8057', ['buffer' => function () { return false; }]);
        $response->getContent();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Cannot get the content of the response twice: buffering is disabled.');
        $response->getContent();
    }

    public function testReentrantBufferCallback()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057', ['buffer' => function () use (&$response) {
            $response->cancel();
        }]);

        $this->assertSame(200, $response->getStatusCode());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Response has been canceled.');
        $response->getContent();
    }

    public function testThrowingBufferCallback()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057', ['buffer' => function () {
            throw new \Exception('Boo');
        }]);

        $this->assertSame(200, $response->getStatusCode());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Boo');
        $response->getContent();
    }
}

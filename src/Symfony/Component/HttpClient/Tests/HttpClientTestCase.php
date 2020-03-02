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
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase as BaseHttpClientTestCase;

/*
Tests for HTTP2 Push need a recent version of both PHP and curl. This docker command should run them:
docker run -it --rm -v $(pwd):/app -v /path/to/vulcain:/usr/local/bin/vulcain -w /app php:7.3-alpine ./phpunit src/Symfony/Component/HttpClient --filter Push
The vulcain binary can be found at https://github.com/symfony/binary-utils/releases/download/v0.1/vulcain_0.1.3_Linux_x86_64.tar.gz - see https://github.com/dunglas/vulcain for source
*/

abstract class HttpClientTestCase extends BaseHttpClientTestCase
{
    private static $vulcainStarted = false;

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

    public function testNonBlockingStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');
        $stream = $response->toStream();

        $this->assertTrue(stream_set_blocking($stream, false));
        $this->assertSame('<1>', fread($stream, 8192));
        $this->assertFalse(feof($stream));

        $this->assertTrue(stream_set_blocking($stream, true));
        $this->assertSame('<2>', fread($stream, 8192));
        $this->assertSame('', fread($stream, 8192));
        $this->assertTrue(feof($stream));
    }

    public function testResponseStreamRewind()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/103');

        $stream = $response->toStream();

        $this->assertSame('Here the body', stream_get_contents($stream));
        rewind($stream);
        $this->assertSame('Here the body', stream_get_contents($stream));
    }

    public function testStreamWrapperStreamRewind()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/103');

        $stream = StreamWrapper::createResource($response);

        $this->assertSame('Here the body', stream_get_contents($stream));
        rewind($stream);
        $this->assertSame('Here the body', stream_get_contents($stream));
    }

    public function testStreamWrapperWithClientStreamRewind()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/103');

        $stream = StreamWrapper::createResource($response, $client);

        $this->assertSame('Here the body', stream_get_contents($stream));
        rewind($stream);
        $this->assertSame('Here the body', stream_get_contents($stream));
    }

    public function testHttp2PushVulcain()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        self::startVulcain($client);
        $logger = new TestLogger();
        $client->setLogger($logger);

        $responseAsArray = $client->request('GET', 'https://127.0.0.1:3000/json', [
            'headers' => [
                'Preload' => '/documents/*/id',
            ],
        ])->toArray();

        foreach ($responseAsArray['documents'] as $document) {
            $client->request('GET', 'https://127.0.0.1:3000'.$document['id'])->toArray();
        }

        $client->reset();

        $expected = [
            'Request: "GET https://127.0.0.1:3000/json"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/1"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/2"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/3"',
            'Response: "200 https://127.0.0.1:3000/json"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/1"',
            'Response: "200 https://127.0.0.1:3000/json/1"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/2"',
            'Response: "200 https://127.0.0.1:3000/json/2"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/3"',
            'Response: "200 https://127.0.0.1:3000/json/3"',
        ];
        $this->assertSame($expected, $logger->logs);
    }

    public function testHttp2PushVulcainWithUnusedResponse()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        self::startVulcain($client);
        $logger = new TestLogger();
        $client->setLogger($logger);

        $responseAsArray = $client->request('GET', 'https://127.0.0.1:3000/json', [
            'headers' => [
                'Preload' => '/documents/*/id',
            ],
        ])->toArray();

        $i = 0;
        foreach ($responseAsArray['documents'] as $document) {
            $client->request('GET', 'https://127.0.0.1:3000'.$document['id'])->toArray();
            if (++$i >= 2) {
                break;
            }
        }

        $client->reset();

        $expected = [
            'Request: "GET https://127.0.0.1:3000/json"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/1"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/2"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/3"',
            'Response: "200 https://127.0.0.1:3000/json"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/1"',
            'Response: "200 https://127.0.0.1:3000/json/1"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/2"',
            'Response: "200 https://127.0.0.1:3000/json/2"',
            'Unused pushed response: "https://127.0.0.1:3000/json/3"',
        ];
        $this->assertSame($expected, $logger->logs);
    }

    private static function startVulcain(HttpClientInterface $client)
    {
        if (self::$vulcainStarted) {
            return;
        }

        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Testing with the "vulcain" is not supported on Windows.');
        }

        if (['application/json'] !== $client->request('GET', 'http://127.0.0.1:8057/json')->getHeaders()['content-type']) {
            self::markTestSkipped('symfony/http-client-contracts >= 2.0.1 required');
        }

        $process = new Process(['vulcain'], null, [
            'DEBUG' => 1,
            'UPSTREAM' => 'http://127.0.0.1:8057',
            'ADDR' => ':3000',
            'KEY_FILE' => __DIR__.'/Fixtures/tls/server.key',
            'CERT_FILE' => __DIR__.'/Fixtures/tls/server.crt',
        ]);
        $process->start();

        register_shutdown_function([$process, 'stop']);
        sleep('\\' === \DIRECTORY_SEPARATOR ? 10 : 1);

        if (!$process->isRunning()) {
            throw new ProcessFailedException($process);
        }

        self::$vulcainStarted = true;
    }
}

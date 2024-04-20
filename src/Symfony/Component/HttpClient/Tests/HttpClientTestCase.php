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
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase as BaseHttpClientTestCase;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

/*
Tests for HTTP2 Push need a recent version of both PHP and curl. This docker command should run them:
docker run -it --rm -v $(pwd):/app -v /path/to/vulcain:/usr/local/bin/vulcain -w /app php:7.3-alpine ./phpunit src/Symfony/Component/HttpClient --filter Push
The vulcain binary can be found at https://github.com/symfony/binary-utils/releases/download/v0.1/vulcain_0.1.3_Linux_x86_64.tar.gz - see https://github.com/dunglas/vulcain for source
*/

abstract class HttpClientTestCase extends BaseHttpClientTestCase
{
    private static bool $vulcainStarted = false;

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

    public function testSeekAsyncStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');
        $stream = $response->toStream(false);

        $this->assertSame(0, fseek($stream, 0, \SEEK_CUR));
        $this->assertSame('<1>', fread($stream, 8192));
        $this->assertFalse(feof($stream));
        $this->assertSame('<2>', stream_get_contents($stream));
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

    public function testPause()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/');

        $time = microtime(true);
        $response->getInfo('pause_handler')(0.5);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(0.5 <= microtime(true) - $time);

        $response = $client->request('GET', 'http://localhost:8057/');

        $time = microtime(true);
        $response->getInfo('pause_handler')(1);

        foreach ($client->stream($response, 0.5) as $chunk) {
            $this->assertTrue($chunk->isTimeout());
            $response->cancel();
        }
        $response = null;
        $this->assertTrue(1.0 > microtime(true) - $time);
        $this->assertTrue(0.5 <= microtime(true) - $time);
    }

    public function testPauseReplace()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/');

        $time = microtime(true);
        $response->getInfo('pause_handler')(10);
        $response->getInfo('pause_handler')(0.5);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(0.5, microtime(true) - $time);
        $this->assertLessThanOrEqual(5, microtime(true) - $time);
    }

    public function testPauseDuringBody()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');

        $time = microtime(true);
        $this->assertSame(200, $response->getStatusCode());
        $response->getInfo('pause_handler')(1);
        $response->getContent();
        $this->assertGreaterThanOrEqual(1, microtime(true) - $time);
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

    public function testDnsFailure()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://bad.host.test/');

        $this->expectException(TransportException::class);
        $response->getStatusCode();
    }

    private static function startVulcain(HttpClientInterface $client)
    {
        if (self::$vulcainStarted) {
            return;
        }

        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Testing with the "vulcain" is not supported on Windows.');
        }

        $process = new Process(['vulcain'], null, [
            'DEBUG' => 1,
            'UPSTREAM' => 'http://127.0.0.1:8057',
            'ADDR' => ':3000',
            'KEY_FILE' => __DIR__.'/Fixtures/tls/server.key',
            'CERT_FILE' => __DIR__.'/Fixtures/tls/server.crt',
        ]);

        try {
            $process->start();
        } catch (ProcessFailedException $e) {
            self::markTestSkipped('vulcain failed: '.$e->getMessage());
        }

        register_shutdown_function($process->stop(...));
        sleep('\\' === \DIRECTORY_SEPARATOR ? 10 : 1);

        if (!$process->isRunning()) {
            if ('\\' !== \DIRECTORY_SEPARATOR && 127 === $process->getExitCode()) {
                self::markTestSkipped('vulcain binary is missing');
            }

            if ('\\' !== \DIRECTORY_SEPARATOR && 126 === $process->getExitCode()) {
                self::markTestSkipped('vulcain binary is not executable');
            }

            self::markTestSkipped((new ProcessFailedException($process))->getMessage());
        }

        self::$vulcainStarted = true;
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

    public function testDropContentRelatedHeadersWhenFollowingRequestIsUsingGet()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/302', [
            'body' => 'foo',
            'headers' => ['Content-Length: 3'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
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
        $this->assertStringContainsStringIgnoringCase("\r\nContent-Length: 0", $response->getInfo('debug'));
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

    /**
     * @dataProvider getRedirectWithAuthTests
     */
    public function testRedirectWithAuth(string $url, bool $redirectWithAuth)
    {
        $p = TestHttpServer::start(8067);

        try {
            $client = $this->getHttpClient(__FUNCTION__);

            $response = $client->request('GET', $url, [
                'headers' => [
                    'cookie' => 'foo=bar',
                ],
            ]);
            $body = $response->toArray();
        } finally {
            $p->stop();
        }

        if ($redirectWithAuth) {
            $this->assertArrayHasKey('HTTP_COOKIE', $body);
        } else {
            $this->assertArrayNotHasKey('HTTP_COOKIE', $body);
        }
    }

    public static function getRedirectWithAuthTests()
    {
        return [
            'same host and port' => ['url' => 'http://localhost:8057/302', 'redirectWithAuth' => true],
            'other port' => ['url' => 'http://localhost:8067/302', 'redirectWithAuth' => false],
            'other host' => ['url' => 'http://127.0.0.1:8057/302', 'redirectWithAuth' => false],
        ];
    }

    public function testDefaultContentType()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $client = $client->withOptions(['headers' => ['Content-Type: application/json']]);

        $response = $client->request('POST', 'http://localhost:8057/post', [
            'body' => ['abc' => 'def'],
        ]);

        $this->assertSame(['abc' => 'def', 'REQUEST_METHOD' => 'POST'], $response->toArray());

        $response = $client->request('POST', 'http://localhost:8057/post', [
            'body' => '{"abc": "def"}',
        ]);

        $this->assertSame(['abc' => 'def', 'content-type' => 'application/json', 'REQUEST_METHOD' => 'POST'], $response->toArray());
    }
}

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

use PHPUnit\Framework\SkippedTestSuiteError;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
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

    public function testTimeoutOnDestruct()
    {
        if (!method_exists(parent::class, 'testTimeoutOnDestruct')) {
            self::markTestSkipped('BaseHttpClientTestCase doesn\'t have testTimeoutOnDestruct().');
        }

        parent::testTimeoutOnDestruct();
    }

    public function testAcceptHeader()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057');
        $requestHeaders = $response->toArray();

        self::assertSame('*/*', $requestHeaders['HTTP_ACCEPT']);

        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => [
                'Accept' => 'foo/bar',
            ],
        ]);
        $requestHeaders = $response->toArray();

        self::assertSame('foo/bar', $requestHeaders['HTTP_ACCEPT']);

        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => [
                'Accept' => null,
            ],
        ]);
        $requestHeaders = $response->toArray();

        self::assertArrayNotHasKey('HTTP_ACCEPT', $requestHeaders);
    }

    public function testToStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');
        $stream = $response->toStream();

        self::assertSame("{\n    \"SER", fread($stream, 10));
        self::assertSame('VER_PROTOCOL', fread($stream, 12));
        self::assertFalse(feof($stream));
        self::assertTrue(rewind($stream));

        self::assertIsArray(json_decode(fread($stream, 1024), true));
        self::assertSame('', fread($stream, 1));
        self::assertTrue(feof($stream));
    }

    public function testStreamCopyToStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');
        $h = fopen('php://temp', 'w+');
        stream_copy_to_stream($response->toStream(), $h);

        self::assertTrue(rewind($h));
        self::assertSame("{\n    \"SER", fread($h, 10));
        self::assertSame('VER_PROTOCOL', fread($h, 12));
        self::assertFalse(feof($h));
    }

    public function testToStream404()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/404');
        $stream = $response->toStream(false);

        self::assertSame("{\n    \"SER", fread($stream, 10));
        self::assertSame('VER_PROTOCOL', fread($stream, 12));
        self::assertSame($response, stream_get_meta_data($stream)['wrapper_data']->getResponse());
        self::assertSame(404, $response->getStatusCode());

        $response = $client->request('GET', 'http://localhost:8057/404');
        self::expectException(ClientException::class);
        $response->toStream();
    }

    public function testNonBlockingStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');
        $stream = $response->toStream();
        usleep(10000);

        self::assertTrue(stream_set_blocking($stream, false));
        self::assertSame('<1>', fread($stream, 8192));
        self::assertFalse(feof($stream));

        self::assertTrue(stream_set_blocking($stream, true));
        self::assertSame('<2>', fread($stream, 8192));
        self::assertSame('', fread($stream, 8192));
        self::assertTrue(feof($stream));
    }

    public function testSeekAsyncStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');
        $stream = $response->toStream(false);

        self::assertSame(0, fseek($stream, 0, \SEEK_CUR));
        self::assertSame('<1>', fread($stream, 8192));
        self::assertFalse(feof($stream));
        self::assertSame('<2>', stream_get_contents($stream));
    }

    public function testResponseStreamRewind()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/103');

        $stream = $response->toStream();

        self::assertSame('Here the body', stream_get_contents($stream));
        rewind($stream);
        self::assertSame('Here the body', stream_get_contents($stream));
    }

    public function testStreamWrapperStreamRewind()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/103');

        $stream = StreamWrapper::createResource($response);

        self::assertSame('Here the body', stream_get_contents($stream));
        rewind($stream);
        self::assertSame('Here the body', stream_get_contents($stream));
    }

    public function testStreamWrapperWithClientStreamRewind()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/103');

        $stream = StreamWrapper::createResource($response, $client);

        self::assertSame('Here the body', stream_get_contents($stream));
        rewind($stream);
        self::assertSame('Here the body', stream_get_contents($stream));
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
        self::assertSame($expected, $logger->logs);
    }

    public function testPause()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/');

        $time = microtime(true);
        $response->getInfo('pause_handler')(0.5);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue(0.5 <= microtime(true) - $time);

        $response = $client->request('GET', 'http://localhost:8057/');

        $time = microtime(true);
        $response->getInfo('pause_handler')(1);

        foreach ($client->stream($response, 0.5) as $chunk) {
            self::assertTrue($chunk->isTimeout());
            $response->cancel();
        }
        $response = null;
        self::assertTrue(1.0 > microtime(true) - $time);
        self::assertTrue(0.5 <= microtime(true) - $time);
    }

    public function testPauseReplace()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/');

        $time = microtime(true);
        $response->getInfo('pause_handler')(10);
        $response->getInfo('pause_handler')(0.5);
        self::assertSame(200, $response->getStatusCode());
        self::assertGreaterThanOrEqual(0.5, microtime(true) - $time);
        self::assertLessThanOrEqual(5, microtime(true) - $time);
    }

    public function testPauseDuringBody()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');

        $time = microtime(true);
        self::assertSame(200, $response->getStatusCode());
        $response->getInfo('pause_handler')(1);
        $response->getContent();
        self::assertGreaterThanOrEqual(1, microtime(true) - $time);
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
        self::assertSame($expected, $logger->logs);
    }

    public function testDnsFailure()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://bad.host.test/');

        self::expectException(TransportException::class);
        $response->getStatusCode();
    }

    private static function startVulcain(HttpClientInterface $client)
    {
        if (self::$vulcainStarted) {
            return;
        }

        if ('\\' === \DIRECTORY_SEPARATOR) {
            throw new SkippedTestSuiteError('Testing with the "vulcain" is not supported on Windows.');
        }

        if (['application/json'] !== $client->request('GET', 'http://127.0.0.1:8057/json')->getHeaders()['content-type']) {
            throw new SkippedTestSuiteError('symfony/http-client-contracts >= 2.0.1 required');
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
            if ('\\' !== \DIRECTORY_SEPARATOR && 127 === $process->getExitCode()) {
                throw new SkippedTestSuiteError('vulcain binary is missing');
            }

            if ('\\' !== \DIRECTORY_SEPARATOR && 126 === $process->getExitCode()) {
                throw new SkippedTestSuiteError('vulcain binary is not executable');
            }

            throw new SkippedTestSuiteError((new ProcessFailedException($process))->getMessage());
        }

        self::$vulcainStarted = true;
    }

    public function testHandleIsRemovedOnException()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        try {
            $client->request('GET', 'http://localhost:8057/304');
            self::fail(RedirectionExceptionInterface::class.' expected');
        } catch (RedirectionExceptionInterface $e) {
            // The response content-type mustn't be json as that calls getContent
            // @see src/Symfony/Component/HttpClient/Exception/HttpExceptionTrait.php:58
            self::assertStringNotContainsString('json', $e->getResponse()->getHeaders(false)['content-type'][0] ?? '');
            unset($e);

            $r = new \ReflectionProperty($client, 'multi');
            $r->setAccessible(true);
            /** @var ClientState $clientState */
            $clientState = $r->getValue($client);

            self::assertCount(0, $clientState->handlesActivity);
            self::assertCount(0, $clientState->openHandles);
        }
    }

    public function testDebugInfoOnDestruct()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $traceInfo = [];
        $client->request('GET', 'http://localhost:8057', ['on_progress' => function (int $dlNow, int $dlSize, array $info) use (&$traceInfo) {
            $traceInfo = $info;
        }]);

        self::assertNotEmpty($traceInfo['debug']);
    }

    public function testFixContentLength()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/post', [
            'body' => 'abc=def',
            'headers' => ['Content-Length: 4'],
        ]);

        $body = $response->toArray();

        self::assertSame(['abc' => 'def', 'REQUEST_METHOD' => 'POST'], $body);
    }

    public function testDropContentRelatedHeadersWhenFollowingRequestIsUsingGet()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/302', [
            'body' => 'foo',
            'headers' => ['Content-Length: 3'],
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testNegativeTimeout()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        self::assertSame(200, $client->request('GET', 'http://localhost:8057', [
            'timeout' => -1,
        ])->getStatusCode());
    }

    public function testRedirectAfterPost()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/302/relative', [
            'body' => '',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsStringIgnoringCase("\r\nContent-Length: 0", $response->getInfo('debug'));
    }

    public function testEmptyPut()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('PUT', 'http://localhost:8057/post', [
            'headers' => ['Content-Length' => '0'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString("\r\nContent-Length: ", $response->getInfo('debug'));
    }

    public function testNullBody()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $client->request('POST', 'http://localhost:8057/post', [
            'body' => null,
        ]);

        self::expectNotToPerformAssertions();
    }
}

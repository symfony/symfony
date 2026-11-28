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

use Amp\DeferredFuture;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Amp\async;
use function Amp\delay;
use function Amp\Socket\listen;

#[Group('dns-sensitive')]
class AmpHttpClientTest extends HttpClientTestCase
{
    #[Group('transient')]
    #[Group('transient-on-windows')]
    public function testNonBlockingStream()
    {
        parent::testNonBlockingStream();
    }

    #[Group('transient-on-windows')]
    public function testResolve()
    {
        parent::testResolve();
    }

    #[Group('transient-on-windows')]
    public function testNotATimeout()
    {
        parent::testNotATimeout();
    }

    #[Group('transient-on-windows')]
    public function testTimeoutIsNotAFatalError()
    {
        parent::testTimeoutIsNotAFatalError();
    }

    #[Group('transient-on-windows')]
    public function testTimeoutOnInitialize()
    {
        parent::testTimeoutOnInitialize();
    }

    #[Group('transient-on-windows')]
    public function testTimeoutOnDestruct()
    {
        parent::testTimeoutOnDestruct();
    }

    #[Group('transient-on-windows')]
    public function testPauseDuringBodyLongerThanTimeout()
    {
        parent::testPauseDuringBodyLongerThanTimeout();
    }

    #[Group('transient-on-windows')]
    public function testMaxConnectDuration()
    {
        parent::testMaxConnectDuration();
    }

    #[RequiresPhpExtension('zlib')]
    public function testZlibStreamBody()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $body = fopen('compress.zlib://data:application/gzip;base64,'.base64_encode(gzencode('foo=0123456789')), 'r');

        $response = $client->request('POST', 'http://localhost:'.self::port().'/post', ['body' => $body]);

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray());
    }

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new AmpHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 30]);
    }

    public static function getRedirectWithHostHeaderTests()
    {
        // unlike curl and the native client, amphp keeps the user-provided Host header when the authority doesn't change
        return [
            'same host and port' => ['url' => 'http://localhost:'.self::port().'/custom', 'redirectWithAuth' => true, 'expectedHost' => 'foo.example.com'],
            'other port' => ['url' => 'http://localhost:'.self::port(8067).'/custom', 'redirectWithAuth' => false, 'expectedHost' => 'localhost:'.self::port()],
            'other host' => ['url' => 'http://127.0.0.1:'.self::port().'/custom', 'redirectWithAuth' => false, 'expectedHost' => 'localhost:'.self::port()],
        ];
    }

    public function testProxy()
    {
        $this->markTestSkipped('A real proxy server would be needed.');
    }

    #[TestWith(['/'])]
    #[TestWith(['/timeout-body'])]
    public function testEndOfResponseIsNotDelayed(string $path)
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:'.self::port().$path);
        $lastData = null;

        foreach ($client->stream($response) as $chunk) {
            if ($chunk->isLast()) {
                $this->assertLessThan(0.5, (hrtime(true) - $lastData) / 1E9);
            } elseif ('' !== $chunk->getContent()) {
                $lastData = hrtime(true);
            }
        }
    }

    public function testFailureBeforeHeadersIsNotDelayed()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('POST', 'http://localhost:'.self::port().'/post', ['body' => static function () {
            throw new \RuntimeException('Cannot read the body.');
        }]);

        $start = hrtime(true);

        try {
            $response->getHeaders();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
            $this->assertSame('Cannot read the body.', $e->getMessage());
        }

        $this->assertLessThan(0.5, (hrtime(true) - $start) / 1E9);
    }

    #[TestWith(['GET'])]
    #[TestWith(['HEAD'])]
    public function testCompletedResponseDoesNotBlockOtherResponses(string $method)
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $completed = $client->request($method, 'http://localhost:'.self::port().'/head');
        $this->assertSame(200, $completed->getStatusCode());

        $response = $client->request('GET', 'http://localhost:'.self::port().'/', ['timeout' => 2]);

        $start = hrtime(true);
        $response->getContent();

        $this->assertLessThan(0.5, (hrtime(true) - $start) / 1E9);
    }

    public function testCanceledResponsesDoNotLeakActivity()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $client->request('GET', 'http://localhost:'.self::port().'/timeout-header')->cancel();

        $response = $client->request('GET', 'http://localhost:'.self::port().'/timeout-body');
        $this->assertSame(200, $response->getStatusCode());
        $response->cancel();

        $client->request('GET', 'http://localhost:'.self::port().'/')->getContent();

        $this->assertSame([], (new \ReflectionProperty($client, 'multi'))->getValue($client)->handlesActivity);
    }

    public function testResponseCanceledBeforeTheEventLoopRunsIsNotSent()
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $client = $this->getHttpClient(__FUNCTION__);

        $client->request('GET', 'http://'.stream_socket_get_name($server, false).'/')->cancel();
        $client->request('GET', 'http://localhost:'.self::port().'/')->getContent();

        $this->assertFalse(@stream_socket_accept($server, 0.1));
    }

    public function testConcurrentStreamsWaitOnlyForTheirOwnResponses()
    {
        if (!class_exists(DeferredFuture::class)) {
            $this->markTestSkipped('Concurrent streams need amphp/http-client 5.');
        }

        $server = listen('127.0.0.1:0');
        $busyIsDone = new DeferredFuture();

        async(static function () use ($server, $busyIsDone) {
            while ($socket = $server->accept()) {
                async(static function () use ($socket, $busyIsDone) {
                    $request = '';
                    while (!str_contains($request, "\r\n\r\n") && null !== $data = $socket->read()) {
                        $request .= $data;
                    }

                    if (str_starts_with($request, 'GET /busy ')) {
                        $socket->write("HTTP/1.1 200 OK\r\nContent-Length: 100\r\n\r\n");
                        for ($i = 0; $i < 100; ++$i) {
                            delay(0.001);
                            $socket->write('-');
                        }
                        $busyIsDone->complete();
                    } else {
                        $busyIsDone->getFuture()->await();
                        $socket->write("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
                    }

                    $socket->end();
                });
            }
        });

        $client = $this->getHttpClient(__FUNCTION__);
        $url = 'http://'.$server->getAddress().'/';
        $progressCalls = 0;
        $idle = $client->request('GET', $url.'idle', ['on_progress' => static function () use (&$progressCalls) {
            ++$progressCalls;
        }]);
        $busy = $client->request('GET', $url.'busy');

        $idle = async($idle->getContent(...));
        $busy = async($busy->getContent(...));

        $this->assertSame(str_repeat('-', 100), $busy->await());
        $this->assertSame('', $idle->await());
        $server->close();

        $this->assertLessThan(50, $progressCalls);
    }

    public function testProxyDoesNotReceiveTheFragment()
    {
        $this->markTestSkipped('A real proxy server would be needed.');
    }
}

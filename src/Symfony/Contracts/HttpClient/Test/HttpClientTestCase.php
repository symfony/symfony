<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\HttpClient\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A reference test suite for HttpClientInterface implementations.
 */
abstract class HttpClientTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        TestHttpServer::start();
    }

    abstract protected function getHttpClient(string $testCase): HttpClientInterface;

    public function testGetRequest()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => ['Foo' => 'baR'],
            'user_data' => $data = new \stdClass(),
        ]);

        $this->assertSame([], $response->getInfo('response_headers'));
        $this->assertSame($data, $response->getInfo()['user_data']);
        $this->assertSame(200, $response->getStatusCode());

        $info = $response->getInfo();
        $this->assertNull($info['error']);
        $this->assertSame(0, $info['redirect_count']);
        $this->assertSame('HTTP/1.1 200 OK', $info['response_headers'][0]);
        $this->assertSame('Host: localhost:8057', $info['response_headers'][1]);
        $this->assertSame('http://localhost:8057/', $info['url']);

        $headers = $response->getHeaders();

        $this->assertSame('localhost:8057', $headers['host'][0]);
        $this->assertSame(['application/json'], $headers['content-type']);

        $body = json_decode($response->getContent(), true);
        $this->assertSame($body, $response->toArray());

        $this->assertSame('HTTP/1.1', $body['SERVER_PROTOCOL']);
        $this->assertSame('/', $body['REQUEST_URI']);
        $this->assertSame('GET', $body['REQUEST_METHOD']);
        $this->assertSame('localhost:8057', $body['HTTP_HOST']);
        $this->assertSame('baR', $body['HTTP_FOO']);

        $response = $client->request('GET', 'http://localhost:8057/length-broken');

        $this->expectException(TransportExceptionInterface::class);
        $response->getContent();
    }

    public function testHeadRequest()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('HEAD', 'http://localhost:8057/head', [
            'headers' => ['Foo' => 'baR'],
            'user_data' => $data = new \stdClass(),
            'buffer' => false,
        ]);

        $this->assertSame([], $response->getInfo('response_headers'));
        $this->assertSame(200, $response->getStatusCode());

        $info = $response->getInfo();
        $this->assertSame('HTTP/1.1 200 OK', $info['response_headers'][0]);
        $this->assertSame('Host: localhost:8057', $info['response_headers'][1]);

        $headers = $response->getHeaders();

        $this->assertSame('localhost:8057', $headers['host'][0]);
        $this->assertSame(['application/json'], $headers['content-type']);
        $this->assertTrue(0 < $headers['content-length'][0]);

        $this->assertSame('', $response->getContent());
    }

    public function testNonBufferedGetRequest()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057', [
            'buffer' => false,
            'headers' => ['Foo' => 'baR'],
        ]);

        $body = $response->toArray();
        $this->assertSame('baR', $body['HTTP_FOO']);

        $this->expectException(TransportExceptionInterface::class);
        $response->getContent();
    }

    public function testBufferSink()
    {
        $sink = fopen('php://temp', 'w+');
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057', [
            'buffer' => $sink,
            'headers' => ['Foo' => 'baR'],
        ]);

        $body = $response->toArray();
        $this->assertSame('baR', $body['HTTP_FOO']);

        rewind($sink);
        $sink = stream_get_contents($sink);
        $this->assertSame($sink, $response->getContent());
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

        $this->expectException(TransportExceptionInterface::class);
        $response->getContent();
    }

    public function testReentrantBufferCallback()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057', ['buffer' => function () use (&$response) {
            $response->cancel();

            return true;
        }]);

        $this->assertSame(200, $response->getStatusCode());

        $this->expectException(TransportExceptionInterface::class);
        $response->getContent();
    }

    public function testThrowingBufferCallback()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057', ['buffer' => function () {
            throw new \Exception('Boo.');
        }]);

        $this->assertSame(200, $response->getStatusCode());

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('Boo');
        $response->getContent();
    }

    public function testUnsupportedOption()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->expectException(\InvalidArgumentException::class);
        $client->request('GET', 'http://localhost:8057', [
            'capture_peer_cert' => 1.0,
        ]);
    }

    public function testHttpVersion()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057', [
            'http_version' => 1.0,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('HTTP/1.0 200 OK', $response->getInfo('response_headers')[0]);

        $body = $response->toArray();

        $this->assertSame('HTTP/1.0', $body['SERVER_PROTOCOL']);
        $this->assertSame('GET', $body['REQUEST_METHOD']);
        $this->assertSame('/', $body['REQUEST_URI']);
    }

    public function testChunkedEncoding()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/chunked');

        $this->assertSame(['chunked'], $response->getHeaders()['transfer-encoding']);
        $this->assertSame('Symfony is awesome!', $response->getContent());

        $response = $client->request('GET', 'http://localhost:8057/chunked-broken');

        $this->expectException(TransportExceptionInterface::class);
        $response->getContent();
    }

    public function testClientError()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/404');

        $client->stream($response)->valid();

        $this->assertSame(404, $response->getInfo('http_code'));

        try {
            $response->getHeaders();
            $this->fail(ClientExceptionInterface::class.' expected');
        } catch (ClientExceptionInterface $e) {
        }

        try {
            $response->getContent();
            $this->fail(ClientExceptionInterface::class.' expected');
        } catch (ClientExceptionInterface $e) {
        }

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['application/json'], $response->getHeaders(false)['content-type']);
        $this->assertNotEmpty($response->getContent(false));

        $response = $client->request('GET', 'http://localhost:8057/404');

        try {
            foreach ($client->stream($response) as $chunk) {
                $this->assertTrue($chunk->isFirst());
            }
            $this->fail(ClientExceptionInterface::class.' expected');
        } catch (ClientExceptionInterface $e) {
        }
    }

    public function testIgnoreErrors()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/404');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDnsError()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/301/bad-tld');

        try {
            $response->getStatusCode();
            $this->fail(TransportExceptionInterface::class.' expected');
        } catch (TransportExceptionInterface $e) {
            $this->addToAssertionCount(1);
        }

        try {
            $response->getStatusCode();
            $this->fail(TransportExceptionInterface::class.' still expected');
        } catch (TransportExceptionInterface $e) {
            $this->addToAssertionCount(1);
        }

        $response = $client->request('GET', 'http://localhost:8057/301/bad-tld');

        try {
            foreach ($client->stream($response) as $r => $chunk) {
            }
            $this->fail(TransportExceptionInterface::class.' expected');
        } catch (TransportExceptionInterface $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($response, $r);
        $this->assertNotNull($chunk->getError());

        $this->expectException(TransportExceptionInterface::class);
        foreach ($client->stream($response) as $chunk) {
        }
    }

    public function testInlineAuth()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://foo:bar%3Dbar@localhost:8057');

        $body = $response->toArray();

        $this->assertSame('foo', $body['PHP_AUTH_USER']);
        $this->assertSame('bar=bar', $body['PHP_AUTH_PW']);
    }

    public function testBadRequestBody()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->expectException(TransportExceptionInterface::class);

        $response = $client->request('POST', 'http://localhost:8057/', [
            'body' => function () { yield []; },
        ]);

        $response->getStatusCode();
    }

    public function test304()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/304', [
            'headers' => ['If-Match' => '"abc"'],
            'buffer' => false,
        ]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));
    }

    public function testRedirects()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('POST', 'http://localhost:8057/301', [
            'auth_basic' => 'foo:bar',
            'body' => function () {
                yield 'foo=bar';
            },
        ]);

        $body = $response->toArray();
        $this->assertSame('GET', $body['REQUEST_METHOD']);
        $this->assertSame('Basic Zm9vOmJhcg==', $body['HTTP_AUTHORIZATION']);
        $this->assertSame('http://localhost:8057/', $response->getInfo('url'));

        $this->assertSame(2, $response->getInfo('redirect_count'));
        $this->assertNull($response->getInfo('redirect_url'));

        $expected = [
            'HTTP/1.1 301 Moved Permanently',
            'Location: http://127.0.0.1:8057/302',
            'Content-Type: application/json',
            'HTTP/1.1 302 Found',
            'Location: http://localhost:8057/',
            'Content-Type: application/json',
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
        ];

        $filteredHeaders = array_values(array_filter($response->getInfo('response_headers'), function ($h) {
            return \in_array(substr($h, 0, 4), ['HTTP', 'Loca', 'Cont'], true) && 'Content-Encoding: gzip' !== $h;
        }));

        $this->assertSame($expected, $filteredHeaders);
    }

    public function testInvalidRedirect()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/301/invalid');

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(['//?foo=bar'], $response->getHeaders(false)['location']);
        $this->assertSame(0, $response->getInfo('redirect_count'));
        $this->assertNull($response->getInfo('redirect_url'));

        $this->expectException(RedirectionExceptionInterface::class);
        $response->getHeaders();
    }

    public function testRelativeRedirects()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/302/relative');

        $body = $response->toArray();

        $this->assertSame('/', $body['REQUEST_URI']);
        $this->assertNull($response->getInfo('redirect_url'));

        $response = $client->request('GET', 'http://localhost:8057/302/relative', [
            'max_redirects' => 0,
        ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://localhost:8057/', $response->getInfo('redirect_url'));
    }

    public function testRedirect307()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/307', [
            'body' => function () {
                yield 'foo=bar';
            },
            'max_redirects' => 0,
        ]);

        $this->assertSame(307, $response->getStatusCode());

        $response = $client->request('POST', 'http://localhost:8057/307', [
            'body' => 'foo=bar',
        ]);

        $body = $response->toArray();

        $this->assertSame(['foo' => 'bar', 'REQUEST_METHOD' => 'POST'], $body);
    }

    public function testMaxRedirects()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/301', [
            'max_redirects' => 1,
            'auth_basic' => 'foo:bar',
        ]);

        try {
            $response->getHeaders();
            $this->fail(RedirectionExceptionInterface::class.' expected');
        } catch (RedirectionExceptionInterface $e) {
        }

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(1, $response->getInfo('redirect_count'));
        $this->assertSame('http://localhost:8057/', $response->getInfo('redirect_url'));

        $expected = [
            'HTTP/1.1 301 Moved Permanently',
            'Location: http://127.0.0.1:8057/302',
            'Content-Type: application/json',
            'HTTP/1.1 302 Found',
            'Location: http://localhost:8057/',
            'Content-Type: application/json',
        ];

        $filteredHeaders = array_values(array_filter($response->getInfo('response_headers'), function ($h) {
            return \in_array(substr($h, 0, 4), ['HTTP', 'Loca', 'Cont'], true);
        }));

        $this->assertSame($expected, $filteredHeaders);
    }

    public function testStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057');
        $chunks = $client->stream($response);
        $result = [];

        foreach ($chunks as $r => $chunk) {
            if ($chunk->isTimeout()) {
                $result[] = 't';
            } elseif ($chunk->isLast()) {
                $result[] = 'l';
            } elseif ($chunk->isFirst()) {
                $result[] = 'f';
            }
        }

        $this->assertSame($response, $r);
        $this->assertSame(['f', 'l'], $result);

        $chunk = null;
        $i = 0;

        foreach ($client->stream($response) as $chunk) {
            ++$i;
        }

        $this->assertSame(1, $i);
        $this->assertTrue($chunk->isLast());
    }

    public function testAddToStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $r1 = $client->request('GET', 'http://localhost:8057');

        $completed = [];

        $pool = [$r1];

        while ($pool) {
            $chunks = $client->stream($pool);
            $pool = [];

            foreach ($chunks as $r => $chunk) {
                if (!$chunk->isLast()) {
                    continue;
                }

                if ($r1 === $r) {
                    $r2 = $client->request('GET', 'http://localhost:8057');
                    $pool[] = $r2;
                }

                $completed[] = $r;
            }
        }

        $this->assertSame([$r1, $r2], $completed);
    }

    public function testCompleteTypeError()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->expectException(\TypeError::class);
        $client->stream(123);
    }

    public function testOnProgress()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('POST', 'http://localhost:8057/post', [
            'headers' => ['Content-Length' => 14],
            'body' => 'foo=0123456789',
            'on_progress' => function (...$state) use (&$steps) { $steps[] = $state; },
        ]);

        $body = $response->toArray();

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $body);
        $this->assertSame([0, 0], \array_slice($steps[0], 0, 2));
        $lastStep = \array_slice($steps, -1)[0];
        $this->assertSame([57, 57], \array_slice($lastStep, 0, 2));
        $this->assertSame('http://localhost:8057/post', $steps[0][2]['url']);
    }

    public function testPostJson()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/post', [
            'json' => ['foo' => 'bar'],
        ]);

        $body = $response->toArray();

        $this->assertStringContainsString('json', $body['content-type']);
        unset($body['content-type']);
        $this->assertSame(['foo' => 'bar', 'REQUEST_METHOD' => 'POST'], $body);
    }

    public function testPostArray()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/post', [
            'body' => ['foo' => 'bar'],
        ]);

        $this->assertSame(['foo' => 'bar', 'REQUEST_METHOD' => 'POST'], $response->toArray());
    }

    public function testPostResource()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $h = fopen('php://temp', 'w+');
        fwrite($h, 'foo=0123456789');
        rewind($h);

        $response = $client->request('POST', 'http://localhost:8057/post', [
            'body' => $h,
        ]);

        $body = $response->toArray();

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $body);
    }

    public function testPostCallback()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('POST', 'http://localhost:8057/post', [
            'body' => function () {
                yield 'foo';
                yield '';
                yield '=';
                yield '0123456789';
            },
        ]);

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray());
    }

    public function testCancel()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-header');

        $response->cancel();
        $this->expectException(TransportExceptionInterface::class);
        $response->getHeaders();
    }

    public function testInfoOnCanceledResponse()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057/timeout-header');

        $this->assertFalse($response->getInfo('canceled'));
        $response->cancel();
        $this->assertTrue($response->getInfo('canceled'));
    }

    public function testCancelInStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/404');

        foreach ($client->stream($response) as $chunk) {
            $response->cancel();
        }

        $this->expectException(TransportExceptionInterface::class);

        foreach ($client->stream($response) as $chunk) {
        }
    }

    public function testOnProgressCancel()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body', [
            'on_progress' => function ($dlNow) {
                if (0 < $dlNow) {
                    throw new \Exception('Aborting the request.');
                }
            },
        ]);

        try {
            foreach ($client->stream([$response]) as $chunk) {
            }
            $this->fail(ClientExceptionInterface::class.' expected');
        } catch (TransportExceptionInterface $e) {
            $this->assertSame('Aborting the request.', $e->getPrevious()->getMessage());
        }

        $this->assertNotNull($response->getInfo('error'));
        $this->expectException(TransportExceptionInterface::class);
        $response->getContent();
    }

    public function testOnProgressError()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body', [
            'on_progress' => function ($dlNow) {
                if (0 < $dlNow) {
                    throw new \Error('BUG.');
                }
            },
        ]);

        try {
            foreach ($client->stream([$response]) as $chunk) {
            }
            $this->fail('Error expected');
        } catch (\Error $e) {
            $this->assertSame('BUG.', $e->getMessage());
        }

        $this->assertNotNull($response->getInfo('error'));
        $this->expectException(TransportExceptionInterface::class);
        $response->getContent();
    }

    public function testResolve()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://symfony.com:8057/', [
            'resolve' => ['symfony.com' => '127.0.0.1'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(200, $client->request('GET', 'http://symfony.com:8057/')->getStatusCode());

        $response = null;
        $this->expectException(TransportExceptionInterface::class);
        $client->request('GET', 'http://symfony.com:8057/', ['timeout' => 1]);
    }

    public function testIdnResolve()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://0-------------------------------------------------------------0.com:8057/', [
            'resolve' => ['0-------------------------------------------------------------0.com' => '127.0.0.1'],
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $response = $client->request('GET', 'http://Bücher.example:8057/', [
            'resolve' => ['xn--bcher-kva.example' => '127.0.0.1'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNotATimeout()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-header', [
            'timeout' => 0.9,
        ]);
        sleep(1);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testTimeoutOnAccess()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-header', [
            'timeout' => 0.1,
        ]);

        $this->expectException(TransportExceptionInterface::class);
        $response->getHeaders();
    }

    public function testTimeoutIsNotAFatalError()
    {
        usleep(300000); // wait for the previous test to release the server
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body', [
            'timeout' => 0.3,
        ]);

        try {
            $response->getContent();
            $this->fail(TimeoutExceptionInterface::class.' expected');
        } catch (TimeoutExceptionInterface $e) {
        }

        for ($i = 0; $i < 10; ++$i) {
            try {
                $this->assertSame('<1><2>', $response->getContent());
                break;
            } catch (TimeoutExceptionInterface $e) {
            }
        }

        if (10 === $i) {
            throw $e;
        }
    }

    public function testTimeoutOnStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');

        $this->assertSame(200, $response->getStatusCode());
        $chunks = $client->stream([$response], 0.2);

        $result = [];

        foreach ($chunks as $r => $chunk) {
            if ($chunk->isTimeout()) {
                $result[] = 't';
            } else {
                $result[] = $chunk->getContent();
            }
        }

        $this->assertSame(['<1>', 't'], $result);

        $chunks = $client->stream([$response]);

        foreach ($chunks as $r => $chunk) {
            $this->assertSame('<2>', $chunk->getContent());
            $this->assertSame('<1><2>', $r->getContent());

            return;
        }

        $this->fail('The response should have completed');
    }

    public function testUncheckedTimeoutThrows()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');
        $chunks = $client->stream([$response], 0.1);

        $this->expectException(TransportExceptionInterface::class);

        foreach ($chunks as $r => $chunk) {
        }
    }

    public function testDestruct()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $start = microtime(true);
        $client->request('GET', 'http://localhost:8057/timeout-long');
        $client = null;
        $duration = microtime(true) - $start;

        $this->assertGreaterThan(1, $duration);
        $this->assertLessThan(4, $duration);
    }

    public function testGetContentAfterDestruct()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        try {
            $client->request('GET', 'http://localhost:8057/404');
            $this->fail(ClientExceptionInterface::class.' expected');
        } catch (ClientExceptionInterface $e) {
            $this->assertSame('GET', $e->getResponse()->toArray(false)['REQUEST_METHOD']);
        }
    }

    public function testProxy()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/', [
            'proxy' => 'http://localhost:8057',
        ]);

        $body = $response->toArray();
        $this->assertSame('localhost:8057', $body['HTTP_HOST']);
        $this->assertRegexp('#^http://(localhost|127\.0\.0\.1):8057/$#', $body['REQUEST_URI']);

        $response = $client->request('GET', 'http://localhost:8057/', [
            'proxy' => 'http://foo:b%3Dar@localhost:8057',
        ]);

        $body = $response->toArray();
        $this->assertSame('Basic Zm9vOmI9YXI=', $body['HTTP_PROXY_AUTHORIZATION']);
    }

    public function testNoProxy()
    {
        putenv('no_proxy='.$_SERVER['no_proxy'] = 'example.com, localhost');

        try {
            $client = $this->getHttpClient(__FUNCTION__);
            $response = $client->request('GET', 'http://localhost:8057/', [
                'proxy' => 'http://localhost:8057',
            ]);

            $body = $response->toArray();

            $this->assertSame('HTTP/1.1', $body['SERVER_PROTOCOL']);
            $this->assertSame('/', $body['REQUEST_URI']);
            $this->assertSame('GET', $body['REQUEST_METHOD']);
        } finally {
            putenv('no_proxy');
            unset($_SERVER['no_proxy']);
        }
    }

    /**
     * @requires extension zlib
     */
    public function testAutoEncodingRequest()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');

        $this->assertSame(200, $response->getStatusCode());

        $headers = $response->getHeaders();

        $this->assertSame(['Accept-Encoding'], $headers['vary']);
        $this->assertStringContainsString('gzip', $headers['content-encoding'][0]);

        $body = $response->toArray();

        $this->assertStringContainsString('gzip', $body['HTTP_ACCEPT_ENCODING']);
    }

    public function testBaseUri()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', '../404', [
            'base_uri' => 'http://localhost:8057/abc/',
        ]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['application/json'], $response->getHeaders(false)['content-type']);
    }

    public function testQuery()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/?a=a', [
            'query' => ['b' => 'b'],
        ]);

        $body = $response->toArray();
        $this->assertSame('GET', $body['REQUEST_METHOD']);
        $this->assertSame('/?a=a&b=b', $body['REQUEST_URI']);
    }

    public function testInformationalResponse()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/103');

        $this->assertSame('Here the body', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testInformationalResponseStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/103');

        $chunks = [];
        foreach ($client->stream($response) as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertSame(103, $chunks[0]->getInformationalStatus()[0]);
        $this->assertSame(['</style.css>; rel=preload; as=style', '</script.js>; rel=preload; as=script'], $chunks[0]->getInformationalStatus()[1]['link']);
        $this->assertTrue($chunks[1]->isFirst());
        $this->assertSame('Here the body', $chunks[2]->getContent());
        $this->assertTrue($chunks[3]->isLast());
        $this->assertNull($chunks[3]->getInformationalStatus());

        $this->assertSame(['date', 'content-length'], array_keys($response->getHeaders()));
        $this->assertContains('Link: </style.css>; rel=preload; as=style', $response->getInfo('response_headers'));
    }

    /**
     * @requires extension zlib
     */
    public function testUserlandEncodingRequest()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => ['Accept-Encoding' => 'gzip'],
        ]);

        $headers = $response->getHeaders();

        $this->assertSame(['Accept-Encoding'], $headers['vary']);
        $this->assertStringContainsString('gzip', $headers['content-encoding'][0]);

        $body = $response->getContent();
        $this->assertSame("\x1F", $body[0]);

        $body = json_decode(gzdecode($body), true);
        $this->assertSame('gzip', $body['HTTP_ACCEPT_ENCODING']);
    }

    /**
     * @requires extension zlib
     */
    public function testGzipBroken()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/gzip-broken');

        $this->expectException(TransportExceptionInterface::class);
        $response->getContent();
    }

    public function testMaxDuration()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/max-duration', [
            'max_duration' => 0.1,
        ]);

        $start = microtime(true);

        try {
            $response->getContent();
        } catch (TransportExceptionInterface $e) {
            $this->addToAssertionCount(1);
        }

        $duration = microtime(true) - $start;

        $this->assertLessThan(10, $duration);
    }
}

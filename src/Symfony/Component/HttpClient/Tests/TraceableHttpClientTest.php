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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class TraceableHttpClientTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        TestHttpServer::start();
    }

    public function testItTracesRequest()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->any())
            ->method('request')
            ->with(
                'GET',
                '/foo/bar',
                $this->callback(function ($subject) {
                    $onprogress = $subject['on_progress'];
                    unset($subject['on_progress'], $subject['extra']);
                    $this->assertEquals(['options1' => 'foo'], $subject);

                    return true;
                })
            )
            ->willReturn(MockResponse::fromRequest('GET', '/foo/bar', ['options1' => 'foo'], new MockResponse('hello')))
        ;

        $sut = new TraceableHttpClient($httpClient);

        $sut->request('GET', '/foo/bar', ['options1' => 'foo'])->getContent();

        $this->assertCount(1, $tracedRequests = $sut->getTracedRequests());
        $actualTracedRequest = $tracedRequests[0];
        $this->assertEquals([
            'method' => 'GET',
            'url' => '/foo/bar',
            'options' => ['options1' => 'foo'],
            'info' => [],
            'content' => 'hello',
        ], $actualTracedRequest);

        $sut->request('GET', '/foo/bar', ['options1' => 'foo', 'extra' => ['trace_content' => false]])->getContent();

        $this->assertCount(2, $tracedRequests = $sut->getTracedRequests());
        $actualTracedRequest = $tracedRequests[1];
        $this->assertEquals([
            'method' => 'GET',
            'url' => '/foo/bar',
            'options' => ['options1' => 'foo', 'extra' => ['trace_content' => false]],
            'info' => [],
            'content' => null,
        ], $actualTracedRequest);
    }

    public function testItCollectsInfoOnRealRequest()
    {
        $sut = new TraceableHttpClient(new MockHttpClient());
        $sut->request('GET', 'http://localhost:8057');
        $this->assertCount(1, $tracedRequests = $sut->getTracedRequests());
        $actualTracedRequest = $tracedRequests[0];
        $this->assertSame('GET', $actualTracedRequest['info']['http_method']);
        $this->assertSame('http://localhost:8057/', $actualTracedRequest['info']['url']);
    }

    public function testItExecutesOnProgressOption()
    {
        $sut = new TraceableHttpClient(new MockHttpClient());
        $foo = 0;
        $sut->request('GET', 'http://localhost:8057', ['on_progress' => function (int $dlNow, int $dlSize, array $info) use (&$foo) {
            ++$foo;
        }]);
        $this->assertCount(1, $tracedRequests = $sut->getTracedRequests());
        $actualTracedRequest = $tracedRequests[0];
        $this->assertGreaterThan(0, $foo);
    }

    public function testItResetsTraces()
    {
        $sut = new TraceableHttpClient(new MockHttpClient());
        $sut->request('GET', 'https://example.com/foo/bar');
        $sut->reset();
        $this->assertCount(0, $sut->getTracedRequests());
    }

    public function testStream()
    {
        $sut = new TraceableHttpClient(new NativeHttpClient());
        $response = $sut->request('GET', 'http://localhost:8057/chunked');
        $chunks = [];
        foreach ($sut->stream($response) as $r => $chunk) {
            $chunks[] = $chunk->getContent();
        }
        $this->assertSame($response, $r);
        $this->assertGreaterThan(1, \count($chunks));
        $this->assertSame('Symfony is awesome!', implode('', $chunks));
    }

    public function testToArrayChecksStatusCodeBeforeDecoding()
    {
        $this->expectException(ClientExceptionInterface::class);

        $sut = new TraceableHttpClient(new MockHttpClient($responseFactory = fn (): MockResponse => new MockResponse('Errored.', ['http_code' => 400])));

        $response = $sut->request('GET', 'https://example.com/foo/bar');
        $response->toArray();
    }

    public function testStopwatch()
    {
        $sw = new Stopwatch(true);
        $sut = new TraceableHttpClient(new NativeHttpClient(), $sw);
        $response = $sut->request('GET', 'http://localhost:8057');

        $response->getStatusCode();
        $response->getHeaders();
        $response->getContent();

        $this->assertArrayHasKey('__root__', $sections = $sw->getSections());
        $this->assertCount(1, $events = $sections['__root__']->getEvents());
        $this->assertArrayHasKey('GET http://localhost:8057', $events);
        $this->assertCount(3, $events['GET http://localhost:8057']->getPeriods());
        $this->assertGreaterThan(0.0, $events['GET http://localhost:8057']->getDuration());
    }

    public function testStopwatchError()
    {
        $sw = new Stopwatch(true);
        $sut = new TraceableHttpClient(new NativeHttpClient(), $sw);
        $response = $sut->request('GET', 'http://localhost:8057/404');

        try {
            $response->getContent();
            $this->fail('Response should have thrown an exception');
        } catch (ClientException $e) {
            // no-op
        }

        $this->assertArrayHasKey('__root__', $sections = $sw->getSections());
        $this->assertCount(1, $events = $sections['__root__']->getEvents());
        $this->assertArrayHasKey('GET http://localhost:8057/404', $events);
        $this->assertCount(1, $events['GET http://localhost:8057/404']->getPeriods());
    }

    public function testStopwatchStream()
    {
        $sw = new Stopwatch(true);
        $sut = new TraceableHttpClient(new NativeHttpClient(), $sw);
        $response = $sut->request('GET', 'http://localhost:8057');

        $chunkCount = 0;
        foreach ($sut->stream([$response]) as $chunk) {
            ++$chunkCount;
        }

        $this->assertArrayHasKey('__root__', $sections = $sw->getSections());
        $this->assertCount(1, $events = $sections['__root__']->getEvents());
        $this->assertArrayHasKey('GET http://localhost:8057', $events);
        $this->assertGreaterThanOrEqual($chunkCount, \count($events['GET http://localhost:8057']->getPeriods()));
    }

    public function testStopwatchStreamError()
    {
        $sw = new Stopwatch(true);
        $sut = new TraceableHttpClient(new NativeHttpClient(), $sw);
        $response = $sut->request('GET', 'http://localhost:8057/404');

        try {
            $chunkCount = 0;
            foreach ($sut->stream([$response]) as $chunk) {
                ++$chunkCount;
            }
            $this->fail('Response should have thrown an exception');
        } catch (ClientException $e) {
            // no-op
        }

        $this->assertArrayHasKey('__root__', $sections = $sw->getSections());
        $this->assertCount(1, $events = $sections['__root__']->getEvents());
        $this->assertArrayHasKey('GET http://localhost:8057/404', $events);
        $this->assertGreaterThanOrEqual($chunkCount, \count($events['GET http://localhost:8057/404']->getPeriods()));
    }

    public function testStopwatchDestruct()
    {
        $sw = new Stopwatch(true);
        $sut = new TraceableHttpClient(new NativeHttpClient(), $sw);
        $sut->request('GET', 'http://localhost:8057');

        $this->assertArrayHasKey('__root__', $sections = $sw->getSections());
        $this->assertCount(1, $events = $sections['__root__']->getEvents());
        $this->assertArrayHasKey('GET http://localhost:8057', $events);
        $this->assertCount(1, $events['GET http://localhost:8057']->getPeriods());
        $this->assertGreaterThan(0.0, $events['GET http://localhost:8057']->getDuration());
    }

    public function testWithOptions()
    {
        $sut = new TraceableHttpClient(new NativeHttpClient());

        $sut2 = $sut->withOptions(['base_uri' => 'http://localhost:8057']);

        $response = $sut2->request('GET', '/');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('http://localhost:8057/', $response->getInfo('url'));

        $this->assertCount(1, $sut->getTracedRequests());
    }
}

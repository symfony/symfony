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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\TraceableHttpClient;
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
        $httpClient = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                '/foo/bar',
                $this->callback(function ($subject) {
                    $onprogress = $subject['on_progress'];
                    unset($subject['on_progress']);
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
}

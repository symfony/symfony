<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class HttpClientDataCollectorTest extends TestCase
{
    public function testItCollectsRequestCount()
    {
        TestHttpServer::start();
        $httpClient1 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/',
            ],
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/301',
            ],
        ]);
        $httpClient2 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/404',
            ],
        ]);
        $httpClient3 = $this->httpClientThatHasTracedRequests([]);
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client1', $httpClient1);
        $sut->registerClient('http_client2', $httpClient2);
        $sut->registerClient('http_client3', $httpClient3);
        $this->assertEquals(0, $sut->getRequestCount());
        $sut->collect(new Request(), new Response());
        $this->assertEquals(3, $sut->getRequestCount());
    }

    public function testItCollectsErrorCount()
    {
        TestHttpServer::start();
        $httpClient1 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/',
            ],
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/301',
            ],
        ]);
        $httpClient2 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => '/404',
                'options' => ['base_uri' => 'http://localhost:8057/'],
            ],
        ]);
        $httpClient3 = $this->httpClientThatHasTracedRequests([]);
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client1', $httpClient1);
        $sut->registerClient('http_client2', $httpClient2);
        $sut->registerClient('http_client3', $httpClient3);
        $this->assertEquals(0, $sut->getErrorCount());
        $sut->collect(new Request(), new Response());
        $this->assertEquals(1, $sut->getErrorCount());
    }

    public function testItCollectsErrorCountByClient()
    {
        TestHttpServer::start();
        $httpClient1 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/',
            ],
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/301',
            ],
        ]);
        $httpClient2 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => '/404',
                'options' => ['base_uri' => 'http://localhost:8057/'],
            ],
        ]);
        $httpClient3 = $this->httpClientThatHasTracedRequests([]);
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client1', $httpClient1);
        $sut->registerClient('http_client2', $httpClient2);
        $sut->registerClient('http_client3', $httpClient3);
        $this->assertEquals([], $sut->getClients());
        $sut->collect(new Request(), new Response());
        $collectedData = $sut->getClients();
        $this->assertEquals(0, $collectedData['http_client1']['error_count']);
        $this->assertEquals(1, $collectedData['http_client2']['error_count']);
        $this->assertEquals(0, $collectedData['http_client3']['error_count']);
    }

    public function testItCollectsTracesByClient()
    {
        TestHttpServer::start();
        $httpClient1 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/',
            ],
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/301',
            ],
        ]);
        $httpClient2 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => '/404',
                'options' => ['base_uri' => 'http://localhost:8057/'],
            ],
        ]);
        $httpClient3 = $this->httpClientThatHasTracedRequests([]);
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client1', $httpClient1);
        $sut->registerClient('http_client2', $httpClient2);
        $sut->registerClient('http_client3', $httpClient3);
        $this->assertEquals([], $sut->getClients());
        $sut->collect(new Request(), new Response());
        $collectedData = $sut->getClients();
        $this->assertCount(2, $collectedData['http_client1']['traces']);
        $this->assertCount(1, $collectedData['http_client2']['traces']);
        $this->assertCount(0, $collectedData['http_client3']['traces']);
    }

    public function testItIsEmptyAfterReset()
    {
        TestHttpServer::start();
        $httpClient1 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/',
            ],
        ]);
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client1', $httpClient1);
        $sut->collect(new Request(), new Response());
        $collectedData = $sut->getClients();
        $this->assertCount(1, $collectedData['http_client1']['traces']);
        $sut->reset();
        $this->assertEquals([], $sut->getClients());
        $this->assertEquals(0, $sut->getErrorCount());
        $this->assertEquals(0, $sut->getRequestCount());
    }

    private function httpClientThatHasTracedRequests($tracedRequests): TraceableHttpClient
    {
        $httpClient = new TraceableHttpClient(new NativeHttpClient());

        foreach ($tracedRequests as $request) {
            $response = $httpClient->request($request['method'], $request['url'], $request['options'] ?? []);
            $response->getContent(false); // To avoid exception in ResponseTrait::doDestruct
        }

        return $httpClient;
    }
}

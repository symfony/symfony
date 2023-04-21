<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class HttpClientDataCollectorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        TestHttpServer::start();
    }

    public function testItCollectsRequestCount()
    {
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
        $sut->lateCollect();
        $this->assertEquals(3, $sut->getRequestCount());
    }

    public function testItCollectsErrorCount()
    {
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
        $sut->lateCollect();
        $this->assertEquals(1, $sut->getErrorCount());
    }

    public function testItCollectsErrorCountByClient()
    {
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
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        $this->assertEquals(0, $collectedData['http_client1']['error_count']);
        $this->assertEquals(1, $collectedData['http_client2']['error_count']);
        $this->assertEquals(0, $collectedData['http_client3']['error_count']);
    }

    public function testItCollectsTracesByClient()
    {
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
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        $this->assertCount(2, $collectedData['http_client1']['traces']);
        $this->assertCount(1, $collectedData['http_client2']['traces']);
        $this->assertCount(0, $collectedData['http_client3']['traces']);
    }

    public function testItIsEmptyAfterReset()
    {
        $httpClient1 = $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/',
            ],
        ]);
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client1', $httpClient1);
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        $this->assertCount(1, $collectedData['http_client1']['traces']);
        $sut->reset();
        $this->assertEquals([], $sut->getClients());
        $this->assertEquals(0, $sut->getErrorCount());
        $this->assertEquals(0, $sut->getRequestCount());
    }

    /**
     * @requires extension openssl
     *
     * @dataProvider provideCurlRequests
     */
    public function testItGeneratesCurlCommandsAsExpected(array $request, string $expectedCurlCommand)
    {
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $this->httpClientThatHasTracedRequests([$request]));
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        self::assertCount(1, $collectedData['http_client']['traces']);
        $curlCommand = $collectedData['http_client']['traces'][0]['curlCommand'];
        self::assertEquals(sprintf($expectedCurlCommand, '\\' === \DIRECTORY_SEPARATOR ? '"' : "'"), $curlCommand);
    }

    public static function provideCurlRequests(): iterable
    {
        yield 'GET' => [
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/json',
            ],
            'curl \\
  --compressed \\
  --request GET \\
  --url %1$shttp://localhost:8057/json%1$s \\
  --header %1$sAccept: */*%1$s \\
  --header %1$sAccept-Encoding: gzip%1$s \\
  --header %1$sUser-Agent: Symfony HttpClient (Native)%1$s',
        ];
        yield 'GET with base uri' => [
            [
                'method' => 'GET',
                'url' => '1',
                'options' => [
                    'base_uri' => 'http://localhost:8057/json/',
                ],
            ],
            'curl \\
  --compressed \\
  --request GET \\
  --url %1$shttp://localhost:8057/json/1%1$s \\
  --header %1$sAccept: */*%1$s \\
  --header %1$sAccept-Encoding: gzip%1$s \\
  --header %1$sUser-Agent: Symfony HttpClient (Native)%1$s',
        ];
        yield 'GET with resolve' => [
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/json',
                'options' => [
                    'resolve' => [
                        'localhost' => '127.0.0.1',
                        'example.com' => null,
                    ],
                ],
            ],
            'curl \\
  --compressed \\
  --resolve %1$slocalhost:8057:127.0.0.1%1$s \\
  --request GET \\
  --url %1$shttp://localhost:8057/json%1$s \\
  --header %1$sAccept: */*%1$s \\
  --header %1$sAccept-Encoding: gzip%1$s \\
  --header %1$sUser-Agent: Symfony HttpClient (Native)%1$s',
        ];
        yield 'POST with string body' => [
            [
                'method' => 'POST',
                'url' => 'http://localhost:8057/json',
                'options' => [
                    'body' => 'foobarbaz',
                ],
            ],
            'curl \\
  --compressed \\
  --request POST \\
  --url %1$shttp://localhost:8057/json%1$s \\
  --header %1$sAccept: */*%1$s \\
  --header %1$sContent-Length: 9%1$s \\
  --header %1$sContent-Type: application/x-www-form-urlencoded%1$s \\
  --header %1$sAccept-Encoding: gzip%1$s \\
  --header %1$sUser-Agent: Symfony HttpClient (Native)%1$s \\
  --data %1$sfoobarbaz%1$s',
        ];
        yield 'POST with array body' => [
            [
                'method' => 'POST',
                'url' => 'http://localhost:8057/json',
                'options' => [
                    'body' => [
                        'foo' => 'fooval',
                        'bar' => 'barval',
                        'baz' => 'bazval',
                        'foobar' => [
                            'baz' => 'bazval',
                            'qux' => 'quxval',
                        ],
                        'bazqux' => ['bazquxval1', 'bazquxval2'],
                        'object' => (object) [
                            'fooprop' => 'foopropval',
                            'barprop' => 'barpropval',
                        ],
                        'tostring' => new class() {
                            public function __toString(): string
                            {
                                return 'tostringval';
                            }
                        },
                    ],
                ],
            ],
            'curl \\
  --compressed \\
  --request POST \\
  --url %1$shttp://localhost:8057/json%1$s \\
  --header %1$sAccept: */*%1$s \\
  --header %1$sContent-Type: application/x-www-form-urlencoded%1$s \\
  --header %1$sContent-Length: 211%1$s \\
  --header %1$sAccept-Encoding: gzip%1$s \\
  --header %1$sUser-Agent: Symfony HttpClient (Native)%1$s \\
  --data %1$sfoo=fooval%1$s --data %1$sbar=barval%1$s --data %1$sbaz=bazval%1$s --data %1$sfoobar[baz]=bazval%1$s --data %1$sfoobar[qux]=quxval%1$s --data %1$sbazqux[0]=bazquxval1%1$s --data %1$sbazqux[1]=bazquxval2%1$s --data %1$sobject[fooprop]=foopropval%1$s --data %1$sobject[barprop]=barpropval%1$s --data %1$stostring=tostringval%1$s',
        ];

        // escapeshellarg on Windows replaces double quotes & percent signs with spaces
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            yield 'GET with query' => [
                [
                    'method' => 'GET',
                    'url' => 'http://localhost:8057/?foo=fooval&bar=barval',
                    'options' => [
                        'query' => [
                            'bar' => 'newbarval',
                            'foobar' => [
                                'baz' => 'bazval',
                                'qux' => 'quxval',
                            ],
                            'bazqux' => ['bazquxval1', 'bazquxval2'],
                        ],
                    ],
                ],
                'curl \\
  --compressed \\
  --request GET \\
  --url %1$shttp://localhost:8057/?foo=fooval&bar=newbarval&foobar[baz]=bazval&foobar[qux]=quxval&bazqux[0]=bazquxval1&bazqux[1]=bazquxval2%1$s \\
  --header %1$sAccept: */*%1$s \\
  --header %1$sAccept-Encoding: gzip%1$s \\
  --header %1$sUser-Agent: Symfony HttpClient (Native)%1$s',
            ];
            yield 'POST with json' => [
                [
                    'method' => 'POST',
                    'url' => 'http://localhost:8057/json',
                    'options' => [
                        'json' => [
                            'foo' => [
                                'bar' => 'baz',
                                'qux' => [1.10, 1.0],
                                'fred' => ['<foo>', "'bar'", '"baz"', '&blong&'],
                            ],
                        ],
                    ],
                ],
                'curl \\
  --compressed \\
  --request POST \\
  --url %1$shttp://localhost:8057/json%1$s \\
  --header %1$sContent-Type: application/json%1$s \\
  --header %1$sAccept: */*%1$s \\
  --header %1$sContent-Length: 120%1$s \\
  --header %1$sAccept-Encoding: gzip%1$s \\
  --header %1$sUser-Agent: Symfony HttpClient (Native)%1$s \\
  --data %1$s{"foo":{"bar":"baz","qux":[1.1,1.0],"fred":["\u003Cfoo\u003E","\u0027bar\u0027","\u0022baz\u0022","\u0026blong\u0026"]}}%1$s',
            ];
        }
    }

    /**
     * @requires extension openssl
     */
    public function testItDoesNotFollowRedirectionsWhenGeneratingCurlCommands()
    {
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/301',
                'options' => [
                    'auth_basic' => 'foo:bar',
                ],
            ],
        ]));
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        self::assertCount(1, $collectedData['http_client']['traces']);
        $curlCommand = $collectedData['http_client']['traces'][0]['curlCommand'];
        self::assertEquals(sprintf('curl \\
  --compressed \\
  --request GET \\
  --url %1$shttp://localhost:8057/301%1$s \\
  --header %1$sAccept: */*%1$s \\
  --header %1$sAuthorization: Basic Zm9vOmJhcg==%1$s \\
  --header %1$sAccept-Encoding: gzip%1$s \\
  --header %1$sUser-Agent: Symfony HttpClient (Native)%1$s', '\\' === \DIRECTORY_SEPARATOR ? '"' : "'"), $curlCommand
        );
    }

    /**
     * @requires extension openssl
     */
    public function testItDoesNotGeneratesCurlCommandsForUnsupportedBodyType()
    {
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $this->httpClientThatHasTracedRequests([
            [
                'method' => 'GET',
                'url' => 'http://localhost:8057/json',
                'options' => [
                    'body' => static fn (int $size): string => '',
                ],
            ],
        ]));
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        self::assertCount(1, $collectedData['http_client']['traces']);
        $curlCommand = $collectedData['http_client']['traces'][0]['curlCommand'];
        self::assertNull($curlCommand);
    }

    /**
     * @requires extension openssl
     */
    public function testItDoesNotGeneratesCurlCommandsForNotEncodableBody()
    {
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $this->httpClientThatHasTracedRequests([
            [
                'method' => 'POST',
                'url' => 'http://localhost:8057/json',
                'options' => [
                    'body' => "\0",
                ],
            ],
        ]));
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        self::assertCount(1, $collectedData['http_client']['traces']);
        $curlCommand = $collectedData['http_client']['traces'][0]['curlCommand'];
        self::assertNull($curlCommand);
    }

    /**
     * @requires extension openssl
     */
    public function testItDoesNotGeneratesCurlCommandsForTooBigData()
    {
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $this->httpClientThatHasTracedRequests([
            [
                'method' => 'POST',
                'url' => 'http://localhost:8057/json',
                'options' => [
                    'body' => str_repeat('1', 257000),
                ],
            ],
        ]));
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        self::assertCount(1, $collectedData['http_client']['traces']);
        $curlCommand = $collectedData['http_client']['traces'][0]['curlCommand'];
        self::assertNull($curlCommand);
    }

    private function httpClientThatHasTracedRequests($tracedRequests): TraceableHttpClient
    {
        $httpClient = new TraceableHttpClient(new NativeHttpClient());

        foreach ($tracedRequests as $request) {
            $response = $httpClient->request($request['method'], $request['url'], $request['options'] ?? []);
            $response->getContent(false); // disables exceptions from destructors
        }

        return $httpClient;
    }
}

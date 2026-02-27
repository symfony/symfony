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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
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
        $this->assertSame(0, $sut->getRequestCount());
        $sut->lateCollect();
        $this->assertSame(3, $sut->getRequestCount());
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
        $this->assertSame(0, $sut->getErrorCount());
        $sut->lateCollect();
        $this->assertSame(1, $sut->getErrorCount());
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
        $this->assertSame([], $sut->getClients());
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        $this->assertSame(0, $collectedData['http_client1']['error_count']);
        $this->assertSame(1, $collectedData['http_client2']['error_count']);
        $this->assertSame(0, $collectedData['http_client3']['error_count']);
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
        $this->assertSame([], $sut->getClients());
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
        $this->assertSame([], $sut->getClients());
        $this->assertSame(0, $sut->getErrorCount());
        $this->assertSame(0, $sut->getRequestCount());
    }

    #[DataProvider('provideCurlRequests')]
    public function testItGeneratesCurlCommandsAsExpected(array $request, string $expectedCurlCommand)
    {
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $this->httpClientThatHasTracedRequests([$request]));
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        self::assertCount(1, $collectedData['http_client']['traces']);
        $curlCommand = $collectedData['http_client']['traces'][0]['curlCommand'];

        self::assertSame(str_replace(['"', "'"], '', $expectedCurlCommand), str_replace(['"', "'"], '', $curlCommand));
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
  --url http://localhost:8057/json \\
  --header Accept: */* \\
  --header Accept-Encoding: gzip \\
  --header User-Agent: Symfony HttpClient (Native)',
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
  --url http://localhost:8057/json/1 \\
  --header Accept: */* \\
  --header Accept-Encoding: gzip \\
  --header User-Agent: Symfony HttpClient (Native)',
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
  --resolve localhost:8057:127.0.0.1 \\
  --request GET \\
  --url http://localhost:8057/json \\
  --header Accept: */* \\
  --header Accept-Encoding: gzip \\
  --header User-Agent: Symfony HttpClient (Native)',
        ];
        yield 'POST with string body' => [
            [
                'method' => 'POST',
                'url' => 'http://localhost:8057/json',
                'options' => [
                    'body' => 'foo bar baz',
                ],
            ],
            'curl \\
  --compressed \\
  --request POST \\
  --url http://localhost:8057/json \\
  --header Accept: */* \\
  --header Content-Length: 11 \\
  --header Content-Type: application/x-www-form-urlencoded \\
  --header Accept-Encoding: gzip \\
  --header User-Agent: Symfony HttpClient (Native) \\
  --data-raw foo bar baz',
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
                        'tostring' => new class {
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
  --url http://localhost:8057/json \\
  --header Accept: */* \\
  --header Content-Type: application/x-www-form-urlencoded \\
  --header Content-Length: 211 \\
  --header Accept-Encoding: gzip \\
  --header User-Agent: Symfony HttpClient (Native) \\
  --data-raw foo=fooval --data-raw bar=barval --data-raw baz=bazval --data-raw foobar[baz]=bazval --data-raw foobar[qux]=quxval --data-raw bazqux[0]=bazquxval1 --data-raw bazqux[1]=bazquxval2 --data-raw object[fooprop]=foopropval --data-raw object[barprop]=barpropval --data-raw tostring=tostringval',
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
  --url http://localhost:8057/?foo=fooval&bar=newbarval&foobar[baz]=bazval&foobar[qux]=quxval&bazqux[0]=bazquxval1&bazqux[1]=bazquxval2 \\
  --header Accept: */* \\
  --header Accept-Encoding: gzip \\
  --header User-Agent: Symfony HttpClient (Native)',
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
  --url http://localhost:8057/json \\
  --header Content-Type: application/json \\
  --header Accept: */* \\
  --header Content-Length: 120 \\
  --header Accept-Encoding: gzip \\
  --header User-Agent: Symfony HttpClient (Native) \\
  --data-raw {"foo":{"bar":"baz","qux":[1.1,1.0],"fred":["\u003Cfoo\u003E","\u0027bar\u0027","\u0022baz\u0022","\u0026blong\u0026"]}}',
            ];
        }
    }

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
        self::assertSame('curl \\
  --compressed \\
  --request GET \\
  --url http://localhost:8057/301 \\
  --header Accept: */* \\
  --header Authorization: Basic Zm9vOmJhcg== \\
  --header Accept-Encoding: gzip \\
  --header User-Agent: Symfony HttpClient (Native)', str_replace(['"', "'"], '', $curlCommand)
        );
    }

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

    public function testItDoesGenerateCurlCommandsForBigData()
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
        self::assertNotNull($curlCommand);
    }

    public function testItDoesNotGeneratesCurlCommandsForUploadedFiles()
    {
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $this->httpClientThatHasTracedRequests([
            [
                'method' => 'POST',
                'url' => 'http://localhost:8057/json',
                'options' => [
                    'body' => ['file' => fopen('data://text/plain,', 'r')],
                ],
            ],
        ]));
        $sut->lateCollect();
        $collectedData = $sut->getClients();
        self::assertCount(1, $collectedData['http_client']['traces']);
        $curlCommand = $collectedData['http_client']['traces'][0]['curlCommand'];
        self::assertNull($curlCommand);
    }

    #[RequiresPhpExtension('curl')]
    public function testGeneratingCurlCommandForArraysWithResourcesAndUnreachableHost()
    {
        $httpClient = new TraceableHttpClient(new CurlHttpClient());
        try {
            $httpClient->request('POST', 'http://localhast:8057/', [
                'body' => ['file' => fopen('data://text/plain,', 'r')],
            ]);
        } catch (TransportException) {
        }
        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $httpClient);
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

    #[DataProvider('provideClientIsResetWhenExpectedCases')]
    public function testClientIsResetWhenExpected(\Closure $request, bool $wasReset)
    {
        $mockHttpClient = new class extends MockHttpClient {
            public bool $wasReset = false;

            public function reset(): void
            {
                parent::reset();

                $this->wasReset = true;
            }
        };

        $sut = new HttpClientDataCollector();
        $sut->registerClient('http_client', $traceableHttpClient = new TraceableHttpClient($mockHttpClient));
        $request($traceableHttpClient);
        $sut->lateCollect();

        $this->assertSame($wasReset, $mockHttpClient->wasReset);
    }

    public static function provideClientIsResetWhenExpectedCases(): iterable
    {
        yield [
            static function (TraceableHttpClient $traceableHttpClient) {
                $response = $traceableHttpClient->request('GET', 'http://localhost/');
                $response->getContent();
            },
            true,
        ];

        yield [
            static fn () => null,
            false,
        ];
    }
}

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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

#[CoversClass(CachingHttpClient::class)]
#[Group('time-sensitive')]
class CachingHttpClientTest extends TestCase
{
    private TagAwareAdapterInterface $cacheAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheAdapter = new TagAwareAdapter(new ArrayAdapter());

        if (class_exists(ClockMock::class)) {
            ClockMock::register(TagAwareAdapter::class);
        }
    }

    public function testBypassCacheWhenBodyPresent()
    {
        // If a request has a non-empty body, caching should be bypassed.
        $client = $this->buildClient([
            new MockResponse('cached response', ['http_code' => 200]),
            new MockResponse('non-cached response', ['http_code' => 200]),
        ]);

        // First request with a body; should always call underlying client.
        $options = ['body' => 'non-empty'];
        $client->request('GET', 'http://example.com/foo-bar', $options);
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame('non-cached response', $response->getContent(), 'Request with body should bypass cache.');
    }

    public function testBypassCacheWhenRangeHeaderPresent()
    {
        // If a "range" header is present, caching is bypassed.
        $client = $this->buildClient([
            new MockResponse('first response', ['http_code' => 200]),
            new MockResponse('second response', ['http_code' => 200]),
        ]);

        $options = [
            'headers' => ['Range' => 'bytes=0-100'],
        ];
        $client->request('GET', 'http://example.com/foo-bar', $options);
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame('second response', $response->getContent(), 'Presence of range header must bypass caching.');
    }

    public function testBypassCacheForNonCacheableMethod()
    {
        // Methods not in CACHEABLE_METHODS (e.g. POST) bypass caching.
        $client = $this->buildClient([
            new MockResponse('first response', ['http_code' => 200]),
            new MockResponse('second response', ['http_code' => 200]),
        ]);

        $client->request('POST', 'http://example.com/foo-bar');
        $response = $client->request('POST', 'http://example.com/foo-bar');
        $this->assertSame('second response', $response->getContent(), 'Non-cacheable method must bypass caching.');
    }

    public function testItServesResponseFromCache()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
        $this->assertSame('2', $response->getHeaders()['age'][0]);
    }

    public function testItSupportsVaryHeader()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                    'Vary' => 'Foo, Bar',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        // Request with one set of headers.
        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Foo' => 'foo', 'Bar' => 'bar']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        // Same headers: should return cached "foo".
        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Foo' => 'foo', 'Bar' => 'bar']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        // Different header values: returns a new response.
        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Foo' => 'bar', 'Bar' => 'foo']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testItDoesntServeAStaleResponse()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=5',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        // The first request returns "foo".
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(4);

        // After 4 seconds, the cached response is still considered valid.
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(1);

        // After an extra second the cache expires, so a new response is served.
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testAResponseWithoutExpirationAsStale()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'public',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        // The first request returns "foo".
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        // After an extra second the cache expires, so a new response is served.
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testItRevalidatesAResponseWithNoCacheDirective()
    {
        // Use a private cache (sharedCache = false) so that revalidation is performed.
        $client = $this->buildClient(
            [
                new MockResponse('foo', [
                    'http_code' => 200,
                    'response_headers' => [
                        'Cache-Control' => 'no-cache, max-age=5',
                    ],
                ]),
                new MockResponse('bar'),
            ],
            sharedCache: false);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        // The next request revalidates the response and should fetch "bar".
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testItServesAStaleResponseIfError()
    {
        $client = $this->buildClient(
            [
                new MockResponse('foo', [
                    'http_code' => 404,
                    'response_headers' => [
                        'Cache-Control' => 'max-age=1, stale-if-error=5',
                    ],
                ]),
                new MockResponse('Internal Server Error', ['http_code' => 500]),
            ],
            sharedCache: false);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent(false));

        sleep(5);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent(false));
    }

    public function testPrivateCacheWithSharedCacheFalse()
    {
        $client = $this->buildClient(
            [
                new MockResponse('foo', [
                    'http_code' => 200,
                    'response_headers' => [
                        'Cache-Control' => 'private, max-age=5',
                    ],
                ]),
                new MockResponse('should not be served'),
            ],
            sharedCache: false);

        $response = $client->request('GET', 'http://example.com/test-private');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/test-private');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testItDoesntStoreAResponseWithNoStoreDirective()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'no-store',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testASharedCacheDoesntStoreAResponseFromRequestWithAuthorization()
    {
        $client = $this->buildClient(
            [
                new MockResponse('foo', [
                    'http_code' => 200,
                ]),
                new MockResponse('bar'),
            ],
            [
                'headers' => [
                    'Authorization' => 'foo',
                ],
            ]
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testASharedCacheStoresAResponseWithPublicDirectiveFromRequestWithAuthorization()
    {
        $client = $this->buildClient(
            [
                new MockResponse('foo', [
                    'http_code' => 200,
                    'response_headers' => [
                        'Cache-Control' => 'public, max-age=300',
                    ],
                ]),
                new MockResponse('should not be served'),
            ],
            [
                'headers' => [
                    'Authorization' => 'foo',
                ],
            ],
            true
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testASharedCacheStoresAResponseWithSMaxAgeDirectiveFromRequestWithAuthorization()
    {
        $client = $this->buildClient(
            [
                new MockResponse('foo', [
                    'http_code' => 200,
                    'response_headers' => [
                        'Cache-Control' => 's-maxage=5',
                    ],
                ]),
                new MockResponse('should not be served'),
            ],
            [
                'headers' => [
                    'Authorization' => 'foo',
                ],
            ],
            true
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testASharedCacheDoesntStoreAResponseWithPrivateDirective()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'private, max-age=5',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testAPrivateCacheStoresAResponseWithPrivateDirective()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'private, max-age=5',
                ],
            ]),
            new MockResponse('should not be served'),
        ],
            sharedCache: false
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testASharedCacheDoesntStoreAResponseWithAuthenticationHeader()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                    'Set-Cookie' => 'foo=bar',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testAPrivateCacheStoresAResponseWithAuthenticationHeader()
    {
        $client = $this->buildClient(
            [
                new MockResponse('foo', [
                    'http_code' => 200,
                    'response_headers' => [
                        'Cache-Control' => 'max-age=300',
                        'Set-Cookie' => 'foo=bar',
                    ],
                ]),
                new MockResponse('should not be served'),
            ],
            sharedCache: false
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testCacheMissAfterInvalidation()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('bar'),
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $client->request('DELETE', 'http://example.com/foo-bar');

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testChunkErrorServesStaleResponse()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=1, stale-if-error=3',
                ],
            ]),
            new MockResponse('', ['error' => 'Simulated']),
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testChunkErrorMustRevalidate()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=1, must-revalidate',
                ],
            ]),
            new MockResponse('', ['error' => 'Simulated']),
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(504, $response->getStatusCode());
    }

    public function testExceedingMaxAgeIsCappedByTtl()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ],
            sharedCache: true,
            maxTtl: 10
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(11);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testItCanStreamAsyncResponse()
    {
        $client = $this->buildClient([
            new MockResponse('foo', ['http_code' => 200]),
        ]);

        $response = $client->request('GET', 'http://example.com/foo-bar');

        $this->assertInstanceOf(AsyncResponse::class, $response);

        $collected = '';
        foreach ($client->stream($response) as $chunk) {
            $collected .= $chunk->getContent();
        }

        $this->assertSame('foo', $collected);
    }

    public function testItCanStreamCachedResponse()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
        ]);

        $client->request('GET', 'http://example.com/foo-bar')->getContent(); // warm the cache
        $response = $client->request('GET', 'http://example.com/foo-bar');

        $this->assertInstanceOf(MockResponse::class, $response);

        $collected = '';
        foreach ($client->stream($response) as $chunk) {
            $collected .= $chunk->getContent();
        }

        $this->assertSame('foo', $collected);
    }

    public function testItCanStreamBoth()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client->request('GET', 'http://example.com/foo')->getContent(); // warm the cache
        $cachedResponse = $client->request('GET', 'http://example.com/foo');
        $asyncResponse = $client->request('GET', 'http://example.com/bar');

        $this->assertInstanceOf(MockResponse::class, $cachedResponse);
        $this->assertInstanceOf(AsyncResponse::class, $asyncResponse);

        $collected = '';
        foreach ($client->stream([$asyncResponse, $cachedResponse]) as $chunk) {
            $collected .= $chunk->getContent();
        }

        $this->assertSame('foobar', $collected);
    }

    public function testMultipleChunksResponse()
    {
        $client = $this->buildClient([
            new MockResponse(['chunk1', 'chunk2', 'chunk3'], ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=5']]),
        ]);

        $response = $client->request('GET', 'http://example.com/multi-chunk');
        $content = '';
        foreach ($client->stream($response) as $chunk) {
            $content .= $chunk->getContent();
        }
        $this->assertSame('chunk1chunk2chunk3', $content);

        $response = $client->request('GET', 'http://example.com/multi-chunk');
        $content = '';
        foreach ($client->stream($response) as $chunk) {
            $content .= $chunk->getContent();
        }
        $this->assertSame('chunk1chunk2chunk3', $content);
    }

    public function testConditionalCacheableStatusCodeWithoutExpiration()
    {
        $client = $this->buildClient([
            new MockResponse('redirected', ['http_code' => 302]),
            new MockResponse('new redirect', ['http_code' => 302]),
        ]);

        $response = $client->request('GET', 'http://example.com/redirect');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('redirected', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/redirect');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('new redirect', $response->getContent(false));
    }

    public function testConditionalCacheableStatusCodeWithExpiration()
    {
        $client = $this->buildClient([
            new MockResponse('redirected', [
                'http_code' => 302,
                'response_headers' => ['Cache-Control' => 'max-age=5'],
            ]),
            new MockResponse('should not be served'),
        ]);

        $response = $client->request('GET', 'http://example.com/redirect');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('redirected', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/redirect');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('redirected', $response->getContent(false));
    }

    public function testETagRevalidation()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=5'],
            ]),
            new MockResponse('', ['http_code' => 304, 'response_headers' => ['ETag' => '"abc123"']]),
        ]);

        $response = $client->request('GET', 'http://example.com/etag');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(6);

        $response = $client->request('GET', 'http://example.com/etag');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testLastModifiedRevalidation()
    {
        $lastModified = 'Wed, 21 Oct 2015 07:28:00 GMT';
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['Last-Modified' => $lastModified, 'Cache-Control' => 'max-age=5'],
            ]),
            new MockResponse('', ['http_code' => 304, 'response_headers' => ['Last-Modified' => $lastModified]]),
        ]);

        $response = $client->request('GET', 'http://example.com/last-modified');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(6);

        $response = $client->request('GET', 'http://example.com/last-modified');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testAgeCalculation()
    {
        $client = $this->buildClient([
            new MockResponse('foo', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
        ]);

        $response = $client->request('GET', 'http://example.com/age-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(3);

        $response = $client->request('GET', 'http://example.com/age-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
        $this->assertSame('3', $response->getHeaders()['age'][0]);
    }

    public function testGatewayTimeoutOnMustRevalidateFailure()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['Cache-Control' => 'max-age=1, must-revalidate'],
            ]),
            new MockResponse('server error', ['http_code' => 500]),
        ]);

        $response = $client->request('GET', 'http://example.com/must-revalidate');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/must-revalidate');
        $this->assertSame(504, $response->getStatusCode());
    }

    public function testVaryAsteriskPreventsCaching()
    {
        $client = $this->buildClient([
            new MockResponse('foo', ['http_code' => 200, 'response_headers' => ['Vary' => '*']]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $response = $client->request('GET', 'http://example.com/vary-asterisk');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/vary-asterisk');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testExcludedHeadersAreNotCached()
    {
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                    'Connection' => 'keep-alive',
                    'Proxy-Authenticate' => 'Basic',
                    'Proxy-Authentication-Info' => 'info',
                    'Proxy-Authorization' => 'Bearer token',
                    'Content-Type' => 'text/plain',
                    'X-Custom-Header' => 'custom-value',
                ],
            ]),
            new MockResponse('should not be served', ['http_code' => 200]),
        ]);

        $response = $client->request('GET', 'http://example.com/header-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $cachedResponse = $client->request('GET', 'http://example.com/header-test');
        $this->assertSame(200, $cachedResponse->getStatusCode());
        $this->assertSame('foo', $cachedResponse->getContent());

        $cachedHeaders = $cachedResponse->getHeaders();

        $this->assertArrayNotHasKey('connection', $cachedHeaders);
        $this->assertArrayNotHasKey('proxy-authenticate', $cachedHeaders);
        $this->assertArrayNotHasKey('proxy-authentication-info', $cachedHeaders);
        $this->assertArrayNotHasKey('proxy-authorization', $cachedHeaders);

        $this->assertArrayHasKey('cache-control', $cachedHeaders);
        $this->assertArrayHasKey('content-type', $cachedHeaders);
        $this->assertArrayHasKey('x-custom-header', $cachedHeaders);
    }

    public function testHeuristicFreshnessWithLastModified()
    {
        $lastModified = gmdate('D, d M Y H:i:s T', time() - 3600); // 1 hour ago
        $client = $this->buildClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['Last-Modified' => $lastModified],
            ]),
            new MockResponse('bar'),
        ]);

        // First request caches with heuristic
        $response = $client->request('GET', 'http://example.com/heuristic');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        // Heuristic: 10% of 3600s = 360s; should be fresh within this time
        sleep(359); // 5 minutes

        $response = $client->request('GET', 'http://example.com/heuristic');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        // After heuristic expires
        sleep(2); // Total 361s, past 360s heuristic

        $response = $client->request('GET', 'http://example.com/heuristic');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testResponseInfluencingHeadersAffectCacheKey()
    {
        $client = $this->buildClient([
            new MockResponse('response for en', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('response for fr', ['http_code' => 200]),
        ]);

        // First request with Accept-Language: en
        $response = $client->request('GET', 'http://example.com/lang-test', ['headers' => ['Accept-Language' => 'en']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('response for en', $response->getContent());

        // Same request with Accept-Language: en should return cached response
        $response = $client->request('GET', 'http://example.com/lang-test', ['headers' => ['Accept-Language' => 'en']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('response for en', $response->getContent());

        // Request with Accept-Language: fr should fetch new response
        $response = $client->request('GET', 'http://example.com/lang-test', ['headers' => ['Accept-Language' => 'fr']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('response for fr', $response->getContent());
    }

    public function testUnsafeInvalidationInBypassFlow()
    {
        $client = $this->buildClient([
            new MockResponse('initial get', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('after invalidate', ['http_code' => 200]),
        ]);

        // Warm cache with GET
        $response = $client->request('GET', 'http://example.com/unsafe-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('initial get', $response->getContent());

        // Unsafe POST with body (bypasses cache but invalidates on success)
        $client->request('POST', 'http://example.com/unsafe-test', ['body' => 'invalidate']);

        // Next GET should miss cache and fetch new
        $response = $client->request('GET', 'http://example.com/unsafe-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('after invalidate', $response->getContent());
    }

    public function testNoInvalidationOnErrorInBypassFlow()
    {
        $client = $this->buildClient([
            new MockResponse('initial get', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
            new MockResponse('server error', ['http_code' => 500]),
            new MockResponse('should not be fetched'),
        ]);

        // Warm cache with GET
        $response = $client->request('GET', 'http://example.com/no-invalidate-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('initial get', $response->getContent());

        // Unsafe POST with body (bypasses cache, but 500 shouldn't invalidate)
        $response = $client->request('POST', 'http://example.com/no-invalidate-test', ['body' => 'no invalidate']);
        $this->assertSame(500, $response->getStatusCode());

        // Next GET should hit cache
        $response = $client->request('GET', 'http://example.com/no-invalidate-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('initial get', $response->getContent());
    }

    public function testMultipleValuesForResponseInfluencingHeadersAffectCacheKey()
    {
        // Test that multiple values for a response-influencing header (like Accept-Language)
        // result in different cache keys and don't incorrectly share cached responses.
        $client = $this->buildClient([
            new MockResponse('response for de', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('response for de-fr', ['http_code' => 200]),
            new MockResponse('response for fr', ['http_code' => 200]),
        ]);

        // First request with Accept-Language: de
        $response = $client->request('GET', 'http://example.com/lang-multi', ['headers' => ['Accept-Language' => 'de']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('response for de', $response->getContent());

        // Same request with Accept-Language: de should return cached response
        $response = $client->request('GET', 'http://example.com/lang-multi', ['headers' => ['Accept-Language' => 'de']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('response for de', $response->getContent());

        // Request with multiple Accept-Language values should fetch new response
        // because the cache key includes all header values
        $response = $client->request('GET', 'http://example.com/lang-multi', ['headers' => ['Accept-Language' => ['de', 'fr']]]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('response for de-fr', $response->getContent());

        // Request with only Accept-Language: fr should fetch yet another response
        $response = $client->request('GET', 'http://example.com/lang-multi', ['headers' => ['Accept-Language' => 'fr']]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('response for fr', $response->getContent());
    }

    public function testETagRevalidationWithTraceableClient()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=5'],
            ]),
            new MockResponse('', ['http_code' => 304, 'response_headers' => ['ETag' => '"abc123"']]),
        ]);

        $cachingClient = new CachingHttpClient($mockClient, $this->cacheAdapter);
        $client = new TraceableHttpClient($cachingClient);

        $response = $client->request('GET', 'http://example.com/etag-traceable');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(6);

        $response = $client->request('GET', 'http://example.com/etag-traceable');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testStaleResponseWithTraceableClient()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['Cache-Control' => 'max-age=1, stale-if-error=60'],
            ]),
            new MockResponse('', ['http_code' => 500]),
        ]);

        $cachingClient = new CachingHttpClient($mockClient, $this->cacheAdapter);
        $client = new TraceableHttpClient($cachingClient);

        $response = $client->request('GET', 'http://example.com/stale-traceable');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/stale-traceable');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testETagRevalidationWithNativeHttpClient()
    {
        TestHttpServer::start();

        $client = new TraceableHttpClient(new CachingHttpClient(new NativeHttpClient(), $this->cacheAdapter));

        $response = $client->request('GET', 'http://localhost:8057/304/etag');
        $this->assertSame(200, $response->getStatusCode());
        if (!$body = $response->getContent()) {
            $this->markTestSkipped('Legacy symfony/http-client-contracts in use');
        }

        // the server returns 304 when If-None-Match matches
        $response = $client->request('GET', 'http://localhost:8057/304/etag');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($body, $response->getContent());
    }

    /**
     * @param iterable<MockResponse> $responses
     */
    private function buildClient(iterable $responses, array $defaultOptions = [], bool $sharedCache = true, int $maxTtl = 86400): CachingHttpClient
    {
        return new CachingHttpClient(
            new MockHttpClient($responses),
            $this->cacheAdapter,
            $defaultOptions,
            $sharedCache,
            $maxTtl,
        );
    }
}

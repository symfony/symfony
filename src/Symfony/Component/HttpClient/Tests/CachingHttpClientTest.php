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
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
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

    public function testItComputesAgeFromOldDateHeaderWhenNoAgeHeader()
    {
        $date = gmdate('D, d M Y H:i:s', time() - 60).' GMT';

        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                    'Date' => $date,
                ],
            ]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);
        $client->request('GET', 'http://example.com/foo-bar')->getContent();

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $age = (int) $response->getHeaders()['age'][0];

        $this->assertGreaterThanOrEqual(60, $age);
        $this->assertLessThan(70, $age);
    }

    public function testItIncludesIncomingAgeHeaderWhenServingFromCache()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                    'Age' => '10',
                ],
            ]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);
        $client->request('GET', 'http://example.com/foo-bar')->getContent();

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $age = (int) $response->getHeaders()['age'][0];

        $this->assertGreaterThanOrEqual(12, $age);
        $this->assertLessThan(20, $age);
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

    public function testItDoesNotStoreConnectionNominatedHopByHopHeaders()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control: max-age=300',
                    'Connection: X-Hop-By-Hop',
                    'Keep-Alive: timeout=5',
                    'TE: trailers',
                    'Trailer: X-Trailer',
                    'Transfer-Encoding: chunked',
                    'Upgrade: websocket',
                    'X-Hop-By-Hop: secret',
                    'X-Keep: persisted',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame('secret', $response->getHeaders()['x-hop-by-hop'][0]);
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $headers = $response->getHeaders();

        $this->assertArrayNotHasKey('x-hop-by-hop', $headers);
        $this->assertArrayNotHasKey('keep-alive', $headers);
        $this->assertArrayNotHasKey('te', $headers);
        $this->assertArrayNotHasKey('trailer', $headers);
        $this->assertArrayNotHasKey('transfer-encoding', $headers);
        $this->assertArrayNotHasKey('upgrade', $headers);
        $this->assertSame('persisted', $headers['x-keep'][0]);
    }

    public function testItDoesNotKeepConnectionNominatedHeadersAfter304Revalidation()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'ETag: "abc"',
                    'Cache-Control: max-age=0',
                    'X-Hop: old',
                ],
            ]),
            new MockResponse('', [
                'http_code' => 304,
                'response_headers' => [
                    'ETag: "abc"',
                    'Connection: X-Hop',
                    'X-Hop: new',
                    'Cache-Control: max-age=300',
                ],
            ]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter, sharedCache: false);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame('old', $response->getHeaders()['x-hop'][0]);
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertArrayNotHasKey('x-hop', $response->getHeaders());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertArrayNotHasKey('x-hop', $response->getHeaders());
        $this->assertSame('foo', $response->getContent());
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

    public function testSharedCacheDoesNotServeStaleResponseOnErrorWhenExpiredByMixedCaseSMaxAge()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 404,
                'response_headers' => [
                    'Cache-Control' => 'S-MaxAge=1, max-age=100, stale-if-error=5',
                ],
            ]),
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent(false));

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(504, $response->getStatusCode());
    }

    public function testSharedCacheServesStaleResponseOnErrorWithMalformedSMaxAgeAndStaleIfError()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 404,
                'response_headers' => [
                    'Cache-Control' => 's-maxage=abc, stale-if-error=9999999999',
                ],
            ]),
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-malformed-s-maxage-stale-if-error');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/foo-bar-malformed-s-maxage-stale-if-error');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent(false));
    }

    public function testSharedCacheServesStaleResponseOnErrorWithDuplicateSMaxAgeAndStaleIfError()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 404,
                'response_headers' => [
                    'Cache-Control' => 's-maxage=1, s-maxage=2, stale-if-error=9999999999',
                ],
            ]),
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-duplicate-s-maxage-stale-if-error');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/foo-bar-duplicate-s-maxage-stale-if-error');
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
                    'Cache-Control' => 'NO-STORE',
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

    public function testItDoesntStoreAResponseWithUppercaseNoStoreDirectiveEvenWithMaxAge()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300, NO-STORE',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-no-store');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-no-store');
        $this->assertSame('bar', $response->getContent());
    }

    public function testItRevalidatesFreshResponseWhenRequestNoCacheDirectiveIsPresent()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
            new MockResponse('baz', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        $this->assertSame('bar', $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'no-cache']])->getContent());
    }

    public function testItRevalidatesFreshResponseWhenRequestMaxAgeZeroDirectiveIsPresent()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        $this->assertSame('bar', $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'max-age=0']])->getContent());
    }

    public function testRequestNoStoreBypassesLookupAndStorage()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        $this->assertSame('bar', $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'no-store']])->getContent());
        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
    }

    public function testOnlyIfCachedReturnsGatewayTimeoutOnCacheMiss()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'only-if-cached']]);
        $this->assertSame(504, $response->getStatusCode());
    }

    public function testOnlyIfCachedReturnsGatewayTimeoutWhenCacheIsBypassed()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'only-if-cached', 'Range' => 'bytes=0-100']]);

        $this->assertSame(504, $response->getStatusCode());
        $this->assertSame(0, $mockClient->getRequestsCount());
    }

    public function testOnlyIfCachedReturnsFreshCachedResponse()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('should not be served', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'only-if-cached']]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testOnlyIfCachedReturnsGatewayTimeoutOnStaleCachedResponse()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=1',
                ],
            ]),
            new MockResponse('should not be served', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'only-if-cached']]);
        $this->assertSame(504, $response->getStatusCode());
    }

    public function testOnlyIfCachedWithNoCacheReturnsGatewayTimeout()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('should not be served', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'only-if-cached, no-cache']]);

        $this->assertSame(504, $response->getStatusCode());
    }

    public function testRequestMaxAgeRejectsTooOldCachedResponse()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        sleep(2);

        $this->assertSame('bar', $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'max-age=1']])->getContent());
    }

    public function testRequestNoCachePreventsStaleIfErrorFallback()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=1, stale-if-error=60',
                ],
            ]),
            new MockResponse('error', ['http_code' => 500]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'no-cache']]);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('error', $response->getContent(false));
    }

    public function testRequestMaxAgePreventsStaleIfErrorFallbackWhenCachedResponseIsTooOld()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300, stale-if-error=60',
                ],
            ]),
            new MockResponse('error', ['http_code' => 500]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'max-age=1']]);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('error', $response->getContent(false));
    }

    public function testMalformedRequestMaxAgeIsIgnored()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('should not be served', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'max-age=1x']])->getContent());
    }

    public function testRequestCacheControlParsesQuotedMixedCaseMaxAgeDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        $this->assertSame('bar', $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'MaX-aGe="0"']])->getContent());
    }

    public function testRequestCacheControlParsesMixedCaseNoCacheDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/foo-bar')->getContent());
        $this->assertSame('bar', $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Cache-Control' => 'NO-CACHE']])->getContent());
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

    public function testASharedCacheDoesntStoreAResponseWithMalformedSMaxAgeDirectiveFromRequestWithAuthorization()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 's-maxage=abc',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            ['headers' => ['Authorization' => 'foo']],
            sharedCache: true,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-malformed-s-maxage');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-malformed-s-maxage');
        $this->assertSame('bar', $response->getContent());
    }

    public function testItStoresAResponseWithUppercaseMaxAgeDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'MAX-AGE=300',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testItStoresAResponseWithQuotedUppercaseMaxAgeDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'MAX-AGE="300"',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-quoted-max-age');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-quoted-max-age');
        $this->assertSame('foo', $response->getContent());
    }

    public function testItStoresAResponseWithEmptyCacheControlDirectives()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => ',,, max-age=300,,',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-empty-cache-control-directives');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-empty-cache-control-directives');
        $this->assertSame('foo', $response->getContent());
    }

    public function testItDoesntStoreAResponseWithMalformedMaxAgeDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=3x0',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-malformed-max-age');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-malformed-max-age');
        $this->assertSame('bar', $response->getContent());
    }

    public function testItDoesntStoreAResponseWithQuotedCommaInMaxAgeDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age="3,0"',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-quoted-comma-max-age');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-quoted-comma-max-age');
        $this->assertSame('bar', $response->getContent());
    }

    public function testCacheControlDuplicateDirectiveInvalidatesFreshness()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=0, MAX-AGE=300',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-duplicate-max-age');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-duplicate-max-age');
        $this->assertSame('bar', $response->getContent());
    }

    public function testCacheControlDuplicateDirectiveAcrossHeaderLinesInvalidatesFreshness()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => ['max-age=0', 'MAX-AGE=300'],
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-duplicate-max-age-header-lines');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-duplicate-max-age-header-lines');
        $this->assertSame('bar', $response->getContent());
    }

    public function testASharedCacheStoresAResponseWithUppercasePublicDirectiveFromRequestWithAuthorization()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'PUBLIC, max-age=300',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            ['headers' => ['Authorization' => 'foo']],
            sharedCache: true,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-public');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-public');
        $this->assertSame('foo', $response->getContent());
    }

    public function testASharedCacheStoresAResponseWithUppercaseSMaxAgeDirectiveFromRequestWithAuthorization()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'S-MAXAGE=300',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            ['headers' => ['Authorization' => 'foo']],
            sharedCache: true,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-s-maxage');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-s-maxage');
        $this->assertSame('foo', $response->getContent());
    }

    public function testASharedCacheDoesntStoreAResponseWithUppercasePrivateDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'PRIVATE, max-age=300',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            sharedCache: true,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-private');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-private');
        $this->assertSame('bar', $response->getContent());
    }

    public function testASharedCacheDoesntStoreAResponseWithUppercaseNoStoreDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'NO-STORE, max-age=300',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            sharedCache: true,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-no-store-shared');
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar-uppercase-no-store-shared');
        $this->assertSame('bar', $response->getContent());
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

    public function testItCachesHeuristicallyCacheableStatuses()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('method not allowed', ['http_code' => 405, 'response_headers' => ['Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT']]),
            new MockResponse('uri too long', ['http_code' => 414, 'response_headers' => ['Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT']]),
            new MockResponse('not implemented', ['http_code' => 501, 'response_headers' => ['Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT']]),
            new MockResponse('should not be served'),
            new MockResponse('should not be served'),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('method not allowed', $client->request('GET', 'http://example.com/405')->getContent(false));
        $this->assertSame('uri too long', $client->request('GET', 'http://example.com/414')->getContent(false));
        $this->assertSame('not implemented', $client->request('GET', 'http://example.com/501')->getContent(false));

        $this->assertSame('method not allowed', $client->request('GET', 'http://example.com/405')->getContent(false));
        $this->assertSame('uri too long', $client->request('GET', 'http://example.com/414')->getContent(false));
        $this->assertSame('not implemented', $client->request('GET', 'http://example.com/501')->getContent(false));
    }

    public function testItStoresResponsesWithExplicitFreshnessEvenForOtherStatuses()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('legal reasons', [
                'http_code' => 451,
                'response_headers' => [
                    'Cache-Control' => 'max-age=60',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('legal reasons', $client->request('GET', 'http://example.com/451')->getContent(false));
        $this->assertSame('legal reasons', $client->request('GET', 'http://example.com/451')->getContent(false));
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

    public function testHeuristicDefaultCacheableStatusCode()
    {
        $lastModified = gmdate('D, d M Y H:i:s', time() - 1000).' GMT';
        $mockClient = new MockHttpClient([
            new MockResponse('permanent redirect', [
                'http_code' => 308,
                'response_headers' => [
                    'Last-Modified' => $lastModified,
                ],
            ]),
            new MockResponse('should not be served', ['http_code' => 308]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/redirect-308');
        $this->assertSame(308, $response->getStatusCode());
        $this->assertSame('permanent redirect', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/redirect-308');
        $this->assertSame(308, $response->getStatusCode());
        $this->assertSame('permanent redirect', $response->getContent(false));
    }

    public function testExplicitFreshnessStatusCode206IsNotStored()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('partial response one', [
                'http_code' => 206,
                'response_headers' => [
                    'Cache-Control' => 'max-age=60',
                ],
            ]),
            new MockResponse('partial response two', [
                'http_code' => 206,
                'response_headers' => [
                    'Cache-Control' => 'max-age=60',
                ],
            ]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/partial');
        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('partial response one', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/partial');
        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('partial response two', $response->getContent(false));
    }

    public function testExplicitFreshnessStandaloneStatusCode304IsNotStored()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 304,
                'response_headers' => [
                    'Cache-Control' => 'max-age=60',
                ],
            ]),
            new MockResponse('fresh payload', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/not-modified');
        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/not-modified');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fresh payload', $response->getContent(false));
    }

    public function testExplicitFreshnessInformationalStatusCodeIsNotStored()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 103,
                'response_headers' => [
                    'Cache-Control' => 'max-age=60',
                ],
            ]),
            new MockResponse('fresh payload', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/early-hints');
        $this->assertSame(103, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/early-hints');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fresh payload', $response->getContent(false));
    }

    public function testInvalidExpiresDoesNotProvideExplicitFreshness()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('invalid expires', [
                'http_code' => 302,
                'response_headers' => [
                    'Expires' => 'not-a-date',
                ],
            ]),
            new MockResponse('fresh redirect', ['http_code' => 302]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/invalid-expires');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('invalid expires', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/invalid-expires');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('fresh redirect', $response->getContent(false));
    }

    public function testPublicNonDefaultStatusCodeUsesHeuristicFreshness()
    {
        $lastModified = gmdate('D, d M Y H:i:s', time() - 1000).' GMT';
        $mockClient = new MockHttpClient([
            new MockResponse('created once', [
                'http_code' => 201,
                'response_headers' => [
                    'Cache-Control' => 'public',
                    'Last-Modified' => $lastModified,
                ],
            ]),
            new MockResponse('should not be served', ['http_code' => 201]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/created');
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created once', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/created');
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created once', $response->getContent(false));
    }

    public function testNonDefaultStatusCodeWithoutPublicDirectiveDoesNotUseHeuristicFreshness()
    {
        $lastModified = gmdate('D, d M Y H:i:s', time() - 1000).' GMT';
        $mockClient = new MockHttpClient([
            new MockResponse('created once', [
                'http_code' => 201,
                'response_headers' => [
                    'Last-Modified' => $lastModified,
                ],
            ]),
            new MockResponse('created twice', ['http_code' => 201]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/created');
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created once', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/created');
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created twice', $response->getContent(false));
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

    public function testRevalidationUsesUpdatedMetadataFrom304Response()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=1'],
            ]),
            new MockResponse('', [
                'http_code' => 304,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=60', 'X-Revalidated' => 'yes'],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/etag-updated-metadata');
        $this->assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/etag-updated-metadata');
        $this->assertSame('foo', $response->getContent());
        $this->assertSame(['max-age=60'], $response->getHeaders()['cache-control']);
        $this->assertSame(['yes'], $response->getHeaders()['x-revalidated']);

        sleep(2);

        $response = $client->request('GET', 'http://example.com/etag-updated-metadata');
        $this->assertSame('foo', $response->getContent());
    }

    public function testRevalidationRetainsStoredFreshnessWhen304OmitsCacheDirectives()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=60'],
            ]),
            new MockResponse('', [
                'http_code' => 304,
                'response_headers' => ['ETag' => '"abc123"'],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-retain-max-age')->getContent());

        sleep(61);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-retain-max-age')->getContent());
        $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-retain-max-age')->getContent());
    }

    public function test304UpdatedMetadataCanInvalidateCacheability()
    {
        foreach (['no-store', 'private', 'max-age=60'] as $cacheControl) {
            $responseHeaders = ['ETag' => '"abc123"', 'Cache-Control' => $cacheControl];
            if ('max-age=60' === $cacheControl) {
                $responseHeaders['Set-Cookie'] = 'foo=bar';
            }

            $mockClient = new MockHttpClient([
                new MockResponse('foo', [
                    'http_code' => 200,
                    'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=1'],
                ]),
                new MockResponse('', [
                    'http_code' => 304,
                    'response_headers' => $responseHeaders,
                ]),
                new MockResponse('bar', [
                    'http_code' => 200,
                    'response_headers' => ['Cache-Control' => 'max-age=60'],
                ]),
            ]);

            $client = new CachingHttpClient($mockClient, $this->cacheAdapter, sharedCache: true);

            $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-invalidate-'.$cacheControl)->getContent());
            sleep(2);

            $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-invalidate-'.$cacheControl)->getContent());
            $this->assertSame('bar', $client->request('GET', 'http://example.com/etag-invalidate-'.$cacheControl)->getContent());
        }
    }

    public function test304UpdatedMetadataEvictsUncacheableResponseBeforeStaleFallback()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=1, stale-if-error=60'],
            ]),
            new MockResponse('', [
                'http_code' => 304,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'no-store'],
            ]),
            new MockResponse('origin error', ['http_code' => 500]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-no-store-evicts')->getContent());
        sleep(2);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-no-store-evicts')->getContent());

        $response = $client->request('GET', 'http://example.com/etag-no-store-evicts');
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('origin error', $response->getContent(false));
    }

    public function test304VaryChangePreventsReuseWithDifferentVaryHeader()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=1', 'Vary' => 'Accept-Language'],
            ]),
            new MockResponse('', [
                'http_code' => 304,
                'response_headers' => ['ETag' => '"abc123"', 'Vary' => 'Accept-Encoding'],
            ]),
            new MockResponse('bar', [
                'http_code' => 200,
                'response_headers' => ['Cache-Control' => 'max-age=60', 'Vary' => 'Accept-Language'],
            ]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-vary-change', ['headers' => ['Accept-Language' => 'en']])->getContent());
        sleep(2);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-vary-change', ['headers' => ['Accept-Language' => 'en']])->getContent());
        $this->assertSame('bar', $client->request('GET', 'http://example.com/etag-vary-change', ['headers' => ['Accept-Language' => 'fr']])->getContent());
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

    public function testOldDateWithShortMaxAgeIsImmediatelyStale()
    {
        $date = gmdate('D, d M Y H:i:s', time() - 60).' GMT';
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Date' => $date,
                    'Cache-Control' => 'max-age=30',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $this->assertSame('foo', $client->request('GET', 'http://example.com/date-max-age')->getContent());
        $this->assertSame('bar', $client->request('GET', 'http://example.com/date-max-age')->getContent());
    }

    public function testOldDateWithExpiresUsesRemainingTtl()
    {
        $dateTimestamp = time() - 60;
        $date = gmdate('D, d M Y H:i:s', $dateTimestamp).' GMT';
        $expires = gmdate('D, d M Y H:i:s', $dateTimestamp + 300).' GMT';

        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Date' => $date,
                    'Expires' => $expires,
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);
        $client->request('GET', 'http://example.com/date-expires')->getContent();

        sleep(245);

        $this->assertSame('bar', $client->request('GET', 'http://example.com/date-expires')->getContent());
    }

    public function testRevalidationUsesUpdatedMetadataImmediately()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=1'],
            ]),
            new MockResponse('', [
                'http_code' => 304,
                'response_headers' => [
                    'ETag' => '"abc123"',
                    'Date' => gmdate('D, d M Y H:i:s', time() - 10).' GMT',
                    'Cache-Control' => 'max-age=30',
                ],
            ]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);
        $this->assertSame('foo', $client->request('GET', 'http://example.com/etag-metadata')->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/etag-metadata');
        $this->assertSame('foo', $response->getContent());
        $this->assertGreaterThanOrEqual(10, (int) $response->getHeaders()['age'][0]);
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

        // Unsafe POST with body (bypasses cache but invalidates on successful responses)
        $response = $client->request('POST', 'http://example.com/unsafe-test', ['body' => 'invalidate']);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());

        // Next GET should miss cache and fetch new
        $response = $client->request('GET', 'http://example.com/unsafe-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('after invalidate', $response->getContent());
    }

    public function testUnknownMethodInvalidationInBypassFlow()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('initial get', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('after invalidate', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/unknown-method-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('initial get', $response->getContent());

        // Deliberately unknown methods are treated as unsafe and must invalidate cache entries on successful responses.
        $response = $client->request('FOO', 'http://example.com/unknown-method-test', ['body' => 'invalidate']);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());

        $response = $client->request('GET', 'http://example.com/unknown-method-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('after invalidate', $response->getContent());
    }

    public function testNoInvalidationOnInformationalResponseInBypassFlow()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('initial get', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
            new MockResponse('', ['http_code' => 103]),
            new MockResponse('should not be fetched', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/no-invalidate-1xx-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('initial get', $response->getContent());

        $response = $client->request('FOO', 'http://example.com/no-invalidate-1xx-test', ['body' => 'do not invalidate']);
        $this->assertSame(103, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));

        // Informational response must not invalidate.
        $response = $client->request('GET', 'http://example.com/no-invalidate-1xx-test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('initial get', $response->getContent());
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

    public function testConditional304FromUserHeadersIsPassedThroughAndDoesNotFreshenCacheWhenValidatorsDoNotMatch()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('cached', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"cache-etag"', 'Cache-Control' => 'max-age=0'],
            ]),
            new MockResponse('', ['http_code' => 304]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/user-conditional-304');
        $this->assertSame('cached', $response->getContent());

        $response = $client->request('GET', 'http://example.com/user-conditional-304', [
            'headers' => ['If-None-Match' => '"user-etag"'],
        ]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));
    }

    public function testConditional304FromUserHeadersIsPassedThroughAndFreshensCacheWhenValidatorsMatch()
    {
        $requestCount = 0;
        $mockClient = new MockHttpClient(static function () use (&$requestCount) {
            ++$requestCount;

            return match ($requestCount) {
                1 => new MockResponse('cached', [
                    'http_code' => 200,
                    'response_headers' => ['ETag' => '"cache-etag"', 'Cache-Control' => 'max-age=0'],
                ]),
                2 => new MockResponse('', ['http_code' => 304, 'response_headers' => ['ETag' => '"cache-etag"', 'Cache-Control' => 'max-age=60']]),
                default => new MockResponse('should not be requested', ['http_code' => 500]),
            };
        });

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/matching-user-conditional-304');
        $this->assertSame('cached', $response->getContent());

        $response = $client->request('GET', 'http://example.com/matching-user-conditional-304', [
            'headers' => ['If-None-Match' => '"cache-etag"'],
        ]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/matching-user-conditional-304');
        $this->assertSame('cached', $response->getContent());
        $this->assertSame(2, $requestCount);
    }

    public function testConditional304FromCacheRevalidationIsPassedThroughWhenResponseValidatorsDoNotMatch()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('cached', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"cache-etag"', 'Cache-Control' => 'max-age=0'],
            ]),
            new MockResponse('', ['http_code' => 304, 'response_headers' => ['ETag' => '"other-etag"']]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/mismatching-response-validator-304');
        $this->assertSame('cached', $response->getContent());

        $response = $client->request('GET', 'http://example.com/mismatching-response-validator-304');

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));
    }

    public function testRevalidationForwardsExpectedConditionalHeaders()
    {
        $requestCount = 0;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestCount) {
            ++$requestCount;

            if (1 === $requestCount) {
                return new MockResponse('cached', [
                    'http_code' => 200,
                    'response_headers' => ['ETag' => '"cache-etag"', 'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT', 'Cache-Control' => 'max-age=0'],
                ]);
            }

            if (2 === $requestCount) {
                $this->assertSame(['If-None-Match: "user-etag"'], $options['normalized_headers']['if-none-match'] ?? []);
                $this->assertArrayNotHasKey('if-modified-since', $options['normalized_headers']);

                return new MockResponse('', ['http_code' => 304]);
            }

            $this->assertSame(['If-None-Match: "cache-etag"'], $options['normalized_headers']['if-none-match'] ?? []);
            $this->assertArrayHasKey('if-modified-since', $options['normalized_headers']);

            return new MockResponse('', ['http_code' => 304, 'response_headers' => ['ETag' => '"cache-etag"']]);
        });

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/conditional-headers');
        $this->assertSame('cached', $response->getContent());

        $response = $client->request('GET', 'http://example.com/conditional-headers', [
            'headers' => ['If-None-Match' => '"user-etag"'],
        ]);
        $this->assertSame(304, $response->getStatusCode());

        $response = $client->request('GET', 'http://example.com/conditional-headers');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test304StrongEtagDoesNotMatchWeakCachedEtag()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('cached', [
                'http_code' => 200,
                'response_headers' => ['ETag' => 'W/"abc123"', 'Cache-Control' => 'max-age=0'],
            ]),
            new MockResponse('', ['http_code' => 304, 'response_headers' => ['ETag' => '"abc123"']]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/strong-vs-weak');
        $this->assertSame('cached', $response->getContent());

        $response = $client->request('GET', 'http://example.com/strong-vs-weak');
        $this->assertSame(304, $response->getStatusCode());
    }

    public function testFreshCachedResponseHonorsMatchingClientIfNoneMatch()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('cached', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"cache-etag"', 'Cache-Control' => 'max-age=300'],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/fresh-client-conditional');
        $this->assertSame('cached', $response->getContent());

        $response = $client->request('GET', 'http://example.com/fresh-client-conditional', [
            'headers' => ['If-None-Match' => 'W/"cache-etag"'],
        ]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));
    }

    public function testFreshCachedResponseHonorsMatchingClientIfModifiedSince()
    {
        $lastModified = 'Wed, 21 Oct 2015 07:28:00 GMT';
        $mockClient = new MockHttpClient([
            new MockResponse('cached', [
                'http_code' => 200,
                'response_headers' => ['Last-Modified' => $lastModified, 'Cache-Control' => 'max-age=300'],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/fresh-client-modified-since');
        $this->assertSame('cached', $response->getContent());

        $response = $client->request('GET', 'http://example.com/fresh-client-modified-since', [
            'headers' => ['If-Modified-Since' => $lastModified],
        ]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent(false));
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

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testNullMaxTtlTriggersDeprecation()
    {
        $this->expectUserDeprecationMessage('Since symfony/http-client 8.1: Passing null as "$maxTtl" to "Symfony\Component\HttpClient\CachingHttpClient::__construct()" is deprecated, pass a positive integer instead.');

        new CachingHttpClient(new MockHttpClient([]), $this->cacheAdapter, [], true, null);
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

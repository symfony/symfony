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
        $mockClient = new MockHttpClient([
            new MockResponse('cached response', ['http_code' => 200]),
            new MockResponse('non-cached response', ['http_code' => 200]),
        ]);
        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        // First request with a body; should always call underlying client.
        $options = ['body' => 'non-empty'];
        $client->request('GET', 'http://example.com/foo-bar', $options);
        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame('non-cached response', $response->getContent(), 'Request with body should bypass cache.');
    }

    public function testBypassCacheWhenRangeHeaderPresent()
    {
        // If a "range" header is present, caching is bypassed.
        $mockClient = new MockHttpClient([
            new MockResponse('first response', ['http_code' => 200]),
            new MockResponse('second response', ['http_code' => 200]),
        ]);
        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('first response', ['http_code' => 200]),
            new MockResponse('second response', ['http_code' => 200]),
        ]);
        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $client->request('POST', 'http://example.com/foo-bar');
        $response = $client->request('POST', 'http://example.com/foo-bar');
        $this->assertSame('second response', $response->getContent(), 'Non-cacheable method must bypass caching.');
    }

    public function testItServesResponseFromCache()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
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

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
        $this->assertSame('2', $response->getHeaders()['age'][0]);
    }

    public function testItSupportsVaryHeader()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                    'Vary' => 'Foo, Bar',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=5',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'public',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'no-cache, max-age=5',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        // Use a private cache (sharedCache = false) so that revalidation is performed.
        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            sharedCache: false,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 404,
                'response_headers' => [
                    'Cache-Control' => 'max-age=1, stale-if-error=5',
                ],
            ]),
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            sharedCache: false,
        );

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
        $responses = [
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'private, max-age=5',
                ],
            ]),
            new MockResponse('should not be served'),
        ];

        $mockHttpClient = new MockHttpClient($responses);
        $client = new CachingHttpClient(
            $mockHttpClient,
            $this->cacheAdapter,
            sharedCache: false,
        );

        $response = $client->request('GET', 'http://example.com/test-private');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/test-private');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());
    }

    public function testItDoesntStoreAResponseWithNoStoreDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'NO-STORE',
                ],
            ]),
            new MockResponse('bar'),
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

    public function testASharedCacheDoesntStoreAResponseFromRequestWithAuthorization()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            [
                'headers' => [
                    'Authorization' => 'foo',
                ],
            ],
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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'public, max-age=300',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            [
                'headers' => [
                    'Authorization' => 'foo',
                ],
            ],
            sharedCache: true,
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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 's-maxage=5',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            [
                'headers' => [
                    'Authorization' => 'foo',
                ],
            ],
            sharedCache: true,
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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'private, max-age=5',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            sharedCache: true,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testAPrivateCacheStoresAResponseWithPrivateDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'private, max-age=5',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            sharedCache: false,
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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                    'Set-Cookie' => 'foo=bar',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            sharedCache: true,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testAPrivateCacheStoresAResponseWithAuthenticationHeader()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                    'Set-Cookie' => 'foo=bar',
                ],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            sharedCache: false,
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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=1, stale-if-error=3',
                ],
            ]),
            new MockResponse('', ['error' => 'Simulated']),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=1, must-revalidate',
                ],
            ]),
            new MockResponse('', ['error' => 'Simulated']),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        $this->assertSame(504, $response->getStatusCode());
    }

    public function testExceedingMaxAgeIsCappedByTtl()
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

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
            maxTtl: 10,
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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

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
        $mockClient = new MockHttpClient([
            new MockResponse(['chunk1', 'chunk2', 'chunk3'], ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=5']]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('redirected', ['http_code' => 302]),
            new MockResponse('new redirect', ['http_code' => 302]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/redirect');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('redirected', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/redirect');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('new redirect', $response->getContent(false));
    }

    public function testConditionalCacheableStatusCodeWithExpiration()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('redirected', [
                'http_code' => 302,
                'response_headers' => ['Cache-Control' => 'max-age=5'],
            ]),
            new MockResponse('should not be served'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/redirect');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('redirected', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/redirect');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('redirected', $response->getContent(false));
    }

    public function testETagRevalidation()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['ETag' => '"abc123"', 'Cache-Control' => 'max-age=5'],
            ]),
            new MockResponse('', ['http_code' => 304, 'response_headers' => ['ETag' => '"abc123"']]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['Last-Modified' => $lastModified, 'Cache-Control' => 'max-age=5'],
            ]),
            new MockResponse('', ['http_code' => 304, 'response_headers' => ['Last-Modified' => $lastModified]]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['Cache-Control' => 'max-age=1, must-revalidate'],
            ]),
            new MockResponse('server error', ['http_code' => 500]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/must-revalidate');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/must-revalidate');
        $this->assertSame(504, $response->getStatusCode());
    }

    public function testVaryAsteriskPreventsCaching()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', ['http_code' => 200, 'response_headers' => ['Vary' => '*']]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/vary-asterisk');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/vary-asterisk');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bar', $response->getContent());
    }

    public function testExcludedHeadersAreNotCached()
    {
        $mockClient = new MockHttpClient([
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

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => ['Last-Modified' => $lastModified],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('response for en', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('response for fr', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('initial get', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('after invalidate', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('initial get', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
            new MockResponse('server error', ['http_code' => 500]),
            new MockResponse('should not be fetched'),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
        $mockClient = new MockHttpClient([
            new MockResponse('response for de', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'max-age=300',
                ],
            ]),
            new MockResponse('response for de-fr', ['http_code' => 200]),
            new MockResponse('response for fr', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

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
}

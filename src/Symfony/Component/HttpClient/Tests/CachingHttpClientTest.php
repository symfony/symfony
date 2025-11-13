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
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

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
        self::assertSame('non-cached response', $response->getContent(), 'Request with body should bypass cache.');
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
        self::assertSame('second response', $response->getContent(), 'Presence of range header must bypass caching.');
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
        self::assertSame('second response', $response->getContent(), 'Non-cacheable method must bypass caching.');
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
        self::assertSame('2', $response->getHeaders()['age'][0]);
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        // Same headers: should return cached "foo".
        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Foo' => 'foo', 'Bar' => 'bar']]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        // Different header values: returns a new response.
        $response = $client->request('GET', 'http://example.com/foo-bar', ['headers' => ['Foo' => 'bar', 'Bar' => 'foo']]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(5);

        // After 5 seconds, the cached response is still considered valid.
        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(1);

        // After an extra second the cache expires, so a new response is served.
        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        // After an extra second the cache expires, so a new response is served.
        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        // The next request revalidates the response and should fetch "bar".
        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('foo', $response->getContent(false));

        sleep(5);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('foo', $response->getContent(false));
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/test-private');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
    }

    public function testItDoesntStoreAResponseWithNoStoreDirective()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', [
                'http_code' => 200,
                'response_headers' => [
                    'Cache-Control' => 'no-store',
                ],
            ]),
            new MockResponse('bar'),
        ]);

        $client = new CachingHttpClient(
            $mockClient,
            $this->cacheAdapter,
        );

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $client->request('DELETE', 'http://example.com/foo-bar');

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(504, $response->getStatusCode());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(11);

        $response = $client->request('GET', 'http://example.com/foo-bar');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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

        self::assertInstanceOf(AsyncResponse::class, $response);

        $collected = '';
        foreach ($client->stream($response) as $chunk) {
            $collected .= $chunk->getContent();
        }

        self::assertSame('foo', $collected);
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

        self::assertInstanceOf(MockResponse::class, $response);

        $collected = '';
        foreach ($client->stream($response) as $chunk) {
            $collected .= $chunk->getContent();
        }

        self::assertSame('foo', $collected);
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

        self::assertInstanceOf(MockResponse::class, $cachedResponse);
        self::assertInstanceOf(AsyncResponse::class, $asyncResponse);

        $collected = '';
        foreach ($client->stream([$asyncResponse, $cachedResponse]) as $chunk) {
            $collected .= $chunk->getContent();
        }

        self::assertSame('foobar', $collected);
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
        self::assertSame('chunk1chunk2chunk3', $content);

        $response = $client->request('GET', 'http://example.com/multi-chunk');
        $content = '';
        foreach ($client->stream($response) as $chunk) {
            $content .= $chunk->getContent();
        }
        self::assertSame('chunk1chunk2chunk3', $content);
    }

    public function testConditionalCacheableStatusCodeWithoutExpiration()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('redirected', ['http_code' => 302]),
            new MockResponse('new redirect', ['http_code' => 302]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/redirect');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('redirected', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/redirect');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('new redirect', $response->getContent(false));
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
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('redirected', $response->getContent(false));

        $response = $client->request('GET', 'http://example.com/redirect');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('redirected', $response->getContent(false));
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(6);

        $response = $client->request('GET', 'http://example.com/etag');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(6);

        $response = $client->request('GET', 'http://example.com/last-modified');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
    }

    public function testAgeCalculation()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', ['http_code' => 200, 'response_headers' => ['Cache-Control' => 'max-age=300']]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/age-test');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(3);

        $response = $client->request('GET', 'http://example.com/age-test');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());
        self::assertSame('3', $response->getHeaders()['age'][0]);
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        sleep(2);

        $response = $client->request('GET', 'http://example.com/must-revalidate');
        self::assertSame(504, $response->getStatusCode());
    }

    public function testVaryAsteriskPreventsCaching()
    {
        $mockClient = new MockHttpClient([
            new MockResponse('foo', ['http_code' => 200, 'response_headers' => ['Vary' => '*']]),
            new MockResponse('bar', ['http_code' => 200]),
        ]);

        $client = new CachingHttpClient($mockClient, $this->cacheAdapter);

        $response = $client->request('GET', 'http://example.com/vary-asterisk');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $response = $client->request('GET', 'http://example.com/vary-asterisk');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        $cachedResponse = $client->request('GET', 'http://example.com/header-test');
        self::assertSame(200, $cachedResponse->getStatusCode());
        self::assertSame('foo', $cachedResponse->getContent());

        $cachedHeaders = $cachedResponse->getHeaders();

        self::assertArrayNotHasKey('connection', $cachedHeaders);
        self::assertArrayNotHasKey('proxy-authenticate', $cachedHeaders);
        self::assertArrayNotHasKey('proxy-authentication-info', $cachedHeaders);
        self::assertArrayNotHasKey('proxy-authorization', $cachedHeaders);

        self::assertArrayHasKey('cache-control', $cachedHeaders);
        self::assertArrayHasKey('content-type', $cachedHeaders);
        self::assertArrayHasKey('x-custom-header', $cachedHeaders);
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        // Heuristic: 10% of 3600s = 360s; should be fresh within this time
        sleep(360); // 5 minutes

        $response = $client->request('GET', 'http://example.com/heuristic');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('foo', $response->getContent());

        // After heuristic expires
        sleep(1); // Total 361s, past 360s heuristic

        $response = $client->request('GET', 'http://example.com/heuristic');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('response for en', $response->getContent());

        // Same request with Accept-Language: en should return cached response
        $response = $client->request('GET', 'http://example.com/lang-test', ['headers' => ['Accept-Language' => 'en']]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('response for en', $response->getContent());

        // Request with Accept-Language: fr should fetch new response
        $response = $client->request('GET', 'http://example.com/lang-test', ['headers' => ['Accept-Language' => 'fr']]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('response for fr', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('initial get', $response->getContent());

        // Unsafe POST with body (bypasses cache but invalidates on success)
        $client->request('POST', 'http://example.com/unsafe-test', ['body' => 'invalidate']);

        // Next GET should miss cache and fetch new
        $response = $client->request('GET', 'http://example.com/unsafe-test');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('after invalidate', $response->getContent());
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
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('initial get', $response->getContent());

        // Unsafe POST with body (bypasses cache, but 500 shouldn't invalidate)
        $response = $client->request('POST', 'http://example.com/no-invalidate-test', ['body' => 'no invalidate']);
        self::assertSame(500, $response->getStatusCode());

        // Next GET should hit cache
        $response = $client->request('GET', 'http://example.com/no-invalidate-test');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('initial get', $response->getContent());
    }
}

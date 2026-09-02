<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Oidc;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OidcDiscoveryTest extends TestCase
{
    private const URL = 'https://provider.example.com/.well-known/openid-configuration';
    private const ISSUER = 'https://provider.example.com';
    private const CONFIGURATION = [
        'issuer' => self::ISSUER,
        'authorization_endpoint' => 'https://provider.example.com/authorize',
        'token_endpoint' => 'https://provider.example.com/token',
    ];

    public function testGetConfigurationFetchesAndDecodesTheDocument()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            ++$requests;
            $this->assertSame('GET', $method);
            $this->assertSame(self::URL, $url);

            return new JsonMockResponse(self::CONFIGURATION);
        });

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);

        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
        // the second read is served by the cache
        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
        $this->assertSame(1, $requests);
    }

    public function testGetConfigurationRejectsIssuerMismatch()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['issuer' => 'https://attacker.example.com']));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not match the configured issuer');

        $discovery->getConfiguration();
    }

    public function testGetConfigurationAcceptsAnAnnouncedIssuerWithATrailingSlash()
    {
        // providers do announce an issuer ending with a slash (Authentik does), while the
        // configured one is normalized without it: the check must not reject them
        $httpClient = new MockHttpClient(new JsonMockResponse(['issuer' => self::ISSUER.'/']));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);

        $this->assertSame(['issuer' => self::ISSUER.'/'], $discovery->getConfiguration());
    }

    public function testGetConfigurationAcceptsAnExpectedIssuerWithATrailingSlash()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['issuer' => self::ISSUER]));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER.'/');

        $this->assertSame(['issuer' => self::ISSUER], $discovery->getConfiguration());
    }

    public function testGetConfigurationRejectsAnInsecureIssuer()
    {
        // the firewall configuration cannot check a "provider_uri" coming from an
        // environment variable, so the resolved value is checked here instead
        $httpClient = new MockHttpClient(static fn () => throw new \LogicException('The provider must not be contacted.'));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), 'http://provider.example.com/.well-known/openid-configuration', 'http://provider.example.com');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $discovery->getConfiguration();
    }

    public function testGetConfigurationAcceptsAnIssuerWithAnUppercaseScheme()
    {
        // parse_url() returns the scheme as it is written, and a scheme is case-insensitive
        $httpClient = new MockHttpClient(new JsonMockResponse(['issuer' => 'HTTPS://provider.example.com']));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, 'HTTPS://provider.example.com');

        $this->assertSame(['issuer' => 'HTTPS://provider.example.com'], $discovery->getConfiguration());
    }

    /**
     * @param string $issuer a loopback host or a name reserved for testing (RFC 2606, RFC 6761)
     */
    #[DataProvider('provideLocalDevelopmentIssuers')]
    public function testGetConfigurationAllowsInsecureIssuersForLocalDevelopment(string $issuer)
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['issuer' => $issuer]));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), $issuer.'/.well-known/openid-configuration', $issuer);

        $this->assertSame(['issuer' => $issuer], $discovery->getConfiguration());
    }

    public static function provideLocalDevelopmentIssuers(): iterable
    {
        yield 'localhost' => ['http://localhost:8080'];
        yield 'IPv4 loopback' => ['http://127.0.0.1:8080'];
        yield 'IPv6 loopback' => ['http://[::1]:8080'];
        yield 'localhost subdomain' => ['http://keycloak.localhost'];
        yield 'test TLD' => ['http://keycloak.test'];
    }

    public function testGetConfigurationSkipsTheIssuerCheckWhenNoIssuerIsExpected()
    {
        // the access-token handlers have no client to issuer mapping, so they opt out
        $httpClient = new MockHttpClient(new JsonMockResponse(['issuer' => 'https://somewhere.example.com']));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL);

        $this->assertSame(['issuer' => 'https://somewhere.example.com'], $discovery->getConfiguration());
    }

    public function testGetConfigurationUsesARelativeUrlByDefault()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame(self::URL, $url);

            return new JsonMockResponse(self::CONFIGURATION);
        }, 'https://provider.example.com/');

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter());

        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
    }

    public function testPrefetchSendsTheRequestThatGetConfigurationConsumes()
    {
        // several documents are fetched concurrently by prefetching them all before reading
        // any of them, which is what OidcTokenHandler does with its discovery clients
        $httpClient = new MockHttpClient(new JsonMockResponse(self::CONFIGURATION));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);
        $discovery->prefetch();

        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
        // the prefetched response is reused, and prefetching stored nothing in the cache
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testPrefetchSendsNothingWhenTheDocumentIsCached()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(self::CONFIGURATION));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);
        $discovery->getConfiguration();

        $discovery->prefetch();

        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testPrefetchDropsItsRequestWhenTheEntryIsWarmedInBetween()
    {
        // another process may store the document between the prefetch and the read: the
        // response is then useless, and must not be consumed the next time the entry
        // expires, hours later, on the same long-running instance
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['issuer' => self::ISSUER, 'token_endpoint' => 'https://provider.example.com/prefetched']),
            new JsonMockResponse(['issuer' => self::ISSUER, 'token_endpoint' => 'https://provider.example.com/fresh']),
        ]);

        $cache = new ArrayAdapter();
        $discovery = new OidcDiscovery($httpClient, $cache, self::URL, self::ISSUER, cacheKey: 'the.key');
        $discovery->prefetch();

        $cache->save($cache->getItem('the.key')->set(['url' => self::URL, 'payload' => json_encode(['issuer' => self::ISSUER, 'token_endpoint' => 'https://provider.example.com/warmed'])]));
        $this->assertSame('https://provider.example.com/warmed', $discovery->getConfiguration()['token_endpoint']);

        $cache->deleteItem('the.key');
        // the memoized document survives the deleted entry until reset()
        $this->assertSame('https://provider.example.com/warmed', $discovery->getConfiguration()['token_endpoint']);

        // after reset(), the dropped prefetch response must not be consumed: the
        // expired entry triggers a new request instead
        $discovery->reset();
        $this->assertSame('https://provider.example.com/fresh', $discovery->getConfiguration()['token_endpoint']);
    }

    public function testGetSecureEndpointReturnsTheAnnouncedEndpoint()
    {
        $discovery = new OidcDiscovery(new MockHttpClient(new JsonMockResponse(self::CONFIGURATION)), new ArrayAdapter(), self::URL, self::ISSUER);

        $this->assertSame('https://provider.example.com/token', $discovery->getSecureEndpoint('token_endpoint'));
    }

    public function testGetSecureEndpointRejectsAnEndpointThatIsNotAnnounced()
    {
        $discovery = new OidcDiscovery(new MockHttpClient(new JsonMockResponse(self::CONFIGURATION)), new ArrayAdapter(), self::URL, self::ISSUER);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not announce any "userinfo_endpoint"');

        $discovery->getSecureEndpoint('userinfo_endpoint');
    }

    public function testGetSecureEndpointRejectsAnInsecureEndpoint()
    {
        // requiring HTTPS on the configured issuer is worth nothing if the endpoints it
        // announces are used as they are: a tampered or misconfigured discovery document
        // would otherwise downgrade the requests carrying the code and the tokens
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'issuer' => self::ISSUER,
            'token_endpoint' => 'http://provider.example.com/token',
        ]));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $discovery->getSecureEndpoint('token_endpoint');
    }

    public function testGetSecureEndpointAllowsALoopbackAuthorizationEndpointForLocalDevelopment()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'issuer' => 'http://localhost:8080',
            'authorization_endpoint' => 'http://localhost:8080/authorize',
        ]));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), 'http://localhost:8080/.well-known/openid-configuration', 'http://localhost:8080');

        $this->assertSame('http://localhost:8080/authorize', $discovery->getSecureEndpoint('authorization_endpoint'));
    }

    public function testGetSecureEndpointRejectsALoopbackTokenEndpoint()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'issuer' => 'http://localhost:8080',
            'token_endpoint' => 'http://localhost:8080/token',
        ]));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), 'http://localhost:8080/.well-known/openid-configuration', 'http://localhost:8080');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $discovery->getSecureEndpoint('token_endpoint');
    }

    public function testGetConfigurationAppendsARelativeUrlToTheIssuer()
    {
        // an issuer coming from an environment variable is a placeholder while the container
        // compiles, so its trailing slash can only be trimmed here: appending the well-known
        // path to "https://provider.example.com/" would otherwise carry a double slash
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame(self::URL, $url);

            return new JsonMockResponse(self::CONFIGURATION);
        });

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), issuer: self::ISSUER.'/');

        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
    }

    public function testGetConfigurationConvertsTransportErrorsToAuthenticationException()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The OIDC discovery document could not be fetched');

        $discovery->getConfiguration();
    }

    public function testGetConfigurationRejectsAnErrorResponseWhoseBodyIsValidJson()
    {
        // an error response whose body happens to decode leaves no JSON failure to fall back on,
        // so the HTTP status itself has to be what rejects the document
        $httpClient = new MockHttpClient(new JsonMockResponse(self::CONFIGURATION, ['http_code' => 500]));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The OIDC discovery document could not be fetched');

        $discovery->getConfiguration();
    }

    public function testGetConfigurationDoesNotCacheAMalformedDocument()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('not json', ['response_headers' => ['content-type' => 'application/json']]),
            new JsonMockResponse(self::CONFIGURATION),
        ]);

        $discovery = new OidcDiscovery($httpClient, $cache = new ArrayAdapter(), self::URL, self::ISSUER);

        try {
            $discovery->getConfiguration();
            $this->fail(\sprintf('Expected an "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException) {
        }

        // the malformed payload was not stored, so the next read hits the provider again
        // instead of replaying the failure until the entry expires
        $this->assertNotContains(serialize('not json'), $cache->getValues());
        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
    }

    public function testGetConfigurationRejectsAnOversizedDocument()
    {
        $httpClient = new MockHttpClient(new MockResponse(str_repeat(' ', 1024 * 1024 + 1)));
        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('exceeds the maximum size');

        $discovery->getConfiguration();
    }

    public function testGetConfigurationCachesTheRawPayload()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(self::CONFIGURATION));

        $discovery = new OidcDiscovery($httpClient, $cache = new ArrayAdapter(), self::URL, self::ISSUER);
        $discovery->getConfiguration();

        // the payload is stored as-is, next to the URL that served it: the URL tells the
        // endpoint checks what transport carried the document, even on a warm cache
        $this->assertSame([['url' => self::URL, 'payload' => json_encode(self::CONFIGURATION)]], array_map(unserialize(...), array_values($cache->getValues())));
    }

    public function testGetConfigurationUsesTheGivenCacheKey()
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('the.custom.key')
            ->willReturn(['url' => self::URL, 'payload' => json_encode(self::CONFIGURATION)]);

        $discovery = new OidcDiscovery($this->createStub(HttpClientInterface::class), $cache, self::URL, self::ISSUER, cacheKey: 'the.custom.key');

        $discovery->getConfiguration();
    }

    public function testGetConfigurationDerivesTheCacheKeyFromTheUrl()
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('oidc_discovery.'.hash('xxh128', self::URL))
            ->willReturn(['url' => self::URL, 'payload' => json_encode(self::CONFIGURATION)]);

        $discovery = new OidcDiscovery($this->createStub(HttpClientInterface::class), $cache, self::URL, self::ISSUER);

        $discovery->getConfiguration();
    }

    public function testGetConfigurationLeavesTheLifetimeToThePoolWhenTtlIsNull()
    {
        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->never())->method('expiresAfter');

        $discovery = new OidcDiscovery(
            new MockHttpClient(new JsonMockResponse(self::CONFIGURATION)),
            $this->cacheRunningCallback($item),
            self::URL,
            self::ISSUER,
            cacheTtl: null,
        );

        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
    }

    public function testGetConfigurationAppliesTheGivenTtl()
    {
        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(60);

        $discovery = new OidcDiscovery(
            new MockHttpClient(new JsonMockResponse(self::CONFIGURATION)),
            $this->cacheRunningCallback($item),
            self::URL,
            self::ISSUER,
            60,
        );

        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
    }

    public function testGetConfigurationRejectsAScalarJsonResponse()
    {
        $httpClient = new MockHttpClient(new MockResponse('123'));

        $discovery = new OidcDiscovery($httpClient, new ArrayAdapter(), self::URL, self::ISSUER);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The document is not a JSON object');

        $discovery->getConfiguration();
    }

    public function testGetConfigurationRejectsAForeignCacheEntry()
    {
        // whatever another writer left under the key is refused, never served as a document
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn('"scalar"');

        $discovery = new OidcDiscovery($httpClient, $cache, self::URL, self::ISSUER);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('could not be fetched');

        $discovery->getConfiguration();
    }

    public function testGetConfigurationMemoizesTheDecodedDocument()
    {
        // a single login callback reads the cache and json_decode()s the document 3 times:
        // once for the issuer check, once for the token_endpoint, once for the userinfo_endpoint,
        // and start() adds one more for the authorization_endpoint. With memoization, a single
        // HTTP request is sent even when the cache TTL is 0 (expiresAfter(0) stores nothing).
        $requests = 0;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            ++$requests;
            $this->assertSame('GET', $method);
            $this->assertSame(self::URL, $url);

            return new JsonMockResponse(self::CONFIGURATION);
        });

        // a cache that never stores (TTL 0)
        $cache = $this->createStub(CacheInterface::class);
        $item = $this->createStub(ItemInterface::class);
        $cache->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            $save = false;

            return $callback($item, $save);
        });

        $discovery = new OidcDiscovery($httpClient, $cache, self::URL, self::ISSUER);

        // first read: getConfiguration()
        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
        // second read: getSecureEndpoint('authorization_endpoint')
        $discovery->getSecureEndpoint('authorization_endpoint');
        // third read: getSecureEndpoint('token_endpoint')
        $discovery->getSecureEndpoint('token_endpoint');

        // only one HTTP request was made thanks to memoization
        $this->assertSame(1, $requests);
    }

    public function testResetClearsTheMemoizedDocument()
    {
        // after reset(), the next read goes back to the cache/HTTP path
        $requests = 0;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            ++$requests;

            return new JsonMockResponse(self::CONFIGURATION);
        });

        // a cache that never stores (TTL 0)
        $cache = $this->createStub(CacheInterface::class);
        $item = $this->createStub(ItemInterface::class);
        $cache->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            $save = false;

            return $callback($item, $save);
        });

        $discovery = new OidcDiscovery($httpClient, $cache, self::URL, self::ISSUER);

        // first read
        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
        $this->assertSame(1, $requests);

        // reset the memo
        $discovery->reset();

        // second read: should hit the cache/HTTP path again
        $this->assertSame(self::CONFIGURATION, $discovery->getConfiguration());
        $this->assertSame(2, $requests);
    }

    private function cacheRunningCallback(ItemInterface $item): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item) {
            $save = true;

            return $callback($item, $save);
        });

        return $cache;
    }
}

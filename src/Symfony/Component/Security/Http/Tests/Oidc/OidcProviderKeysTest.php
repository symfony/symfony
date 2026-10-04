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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Component\Security\Http\Oidc\OidcProviderKeys;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OidcProviderKeysTest extends TestCase
{
    private const SIGNING_JWK = [
        'kid' => 'signing-key',
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'use' => 'sig',
        'alg' => 'ES256',
    ];

    public function testGetKeysReturnsTheSigningKeysTheProviderPublishes()
    {
        $this->assertSame([self::SIGNING_JWK], $this->createProviderKeys()->getSignatureKeys());
    }

    public function testGetKeysDropsTheKeysNotDesignatedForSignature()
    {
        $keys = $this->createProviderKeys(null, null, 3600, [['keys' => [
            ['use' => 'enc'] + self::SIGNING_JWK,
            self::SIGNING_JWK,
        ]]])->getSignatureKeys();

        $this->assertSame([self::SIGNING_JWK], $keys);
    }

    public function testGetKeysFetchesTheKeySetOnce()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse(['keys' => [self::SIGNING_JWK]]);
        });
        $providerKeys = $this->createProviderKeys($httpClient);

        $providerKeys->getSignatureKeys();
        $providerKeys->getSignatureKeys();

        $this->assertSame(1, $requests);
    }

    /**
     * Two readers of one provider share the cache entry.
     */
    public function testGetKeysSharesItsCacheEntryWithAnotherReaderOfTheSameProvider()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse(['keys' => [self::SIGNING_JWK]]);
        });
        $cache = new ArrayAdapter();

        $this->createProviderKeys($httpClient, $cache)->getSignatureKeys();
        $this->createProviderKeys($httpClient, $cache)->getSignatureKeys();

        $this->assertSame(1, $requests);
    }

    /**
     * A reader configured with another lifetime keeps its own entry.
     */
    public function testGetKeysDoesNotShareTheEntryOfAReaderWithAnotherDefaultLifetime()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse(['keys' => [self::SIGNING_JWK]]);
        });
        $cache = new ArrayAdapter();

        $this->createProviderKeys($httpClient, $cache)->getSignatureKeys();
        $this->createProviderKeys($httpClient, $cache, 60)->getSignatureKeys();

        $this->assertSame(2, $requests);
    }

    public function testGetKeysDoesNotShareTheEntryOfALaxReader()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse(['keys' => [self::SIGNING_JWK]]);
        });
        $cache = new ArrayAdapter();

        $this->createProviderKeys($httpClient, $cache)->getSignatureKeys();
        $this->createProviderKeys($httpClient, $cache, 3600, null, null, null, false)->getSignatureKeys();

        $this->assertSame(2, $requests);
    }

    public function testGetKeysRefetchesTheKeySetWhenTheTokenAnnouncesAnUnknownKid()
    {
        $providerKeys = $this->createProviderKeys(null, null, 3600, [
            ['keys' => [self::SIGNING_JWK]],
            ['keys' => [['kid' => 'rotated-key'] + self::SIGNING_JWK]],
        ], null, $clock = new MockClock());
        $providerKeys->getSignatureKeys();

        $clock->sleep(120);

        $this->assertSame([['kid' => 'rotated-key'] + self::SIGNING_JWK], $providerKeys->getSignatureKeys('rotated-key'));
    }

    public function testGetKeysThrottlesTheRefetchOfTheKeySet()
    {
        $providerKeys = $this->createProviderKeys(null, null, 3600, [
            ['keys' => [self::SIGNING_JWK]],
            ['keys' => [['kid' => 'rotated-key'] + self::SIGNING_JWK]],
        ], null, new MockClock());
        $providerKeys->getSignatureKeys();

        // the key set was just fetched, so a token carrying an unknown "kid" does not get
        // to trigger another request: forged tokens cannot drive the outbound traffic
        $this->assertSame([self::SIGNING_JWK], $providerKeys->getSignatureKeys('rotated-key'));
    }

    /**
     * An entry left without any expiry outlives every rotation. A default of zero stands for not
     * caching at all, which is what makes the lifetime observable without waiting for it.
     */
    public function testGetKeysAppliesTheDefaultLifetimeWhenTheProviderAdvertisesNone()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse(['keys' => [self::SIGNING_JWK]]);
        });
        $providerKeys = $this->createProviderKeys($httpClient, null, 0);

        $providerKeys->getSignatureKeys();
        $providerKeys->getSignatureKeys();

        $this->assertSame(2, $requests);
    }

    public function testGetKeysHonorsTheCacheLifetimeTheProviderAdvertises()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse(['keys' => [self::SIGNING_JWK]], ['response_headers' => ['cache-control' => 'max-age=600']]);
        });
        // the lifetime the provider advertises wins over the default, zero included
        $providerKeys = $this->createProviderKeys($httpClient, null, 0);

        $providerKeys->getSignatureKeys();
        $providerKeys->getSignatureKeys();

        $this->assertSame(1, $requests);
    }

    public function testGetKeysRejectsAPlainHttpJwksUri()
    {
        $providerKeys = $this->createProviderKeys(null, null, 3600, null, [
            'issuer' => 'https://provider.example.com',
            'jwks_uri' => 'http://provider.example.com/jwks',
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $providerKeys->getSignatureKeys();
    }

    public function testGetKeysFailsWhenTheProviderAnnouncesNoJwksUri()
    {
        $providerKeys = $this->createProviderKeys(null, null, 3600, null, ['issuer' => 'https://provider.example.com']);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not announce any "jwks_uri"');

        $providerKeys->getSignatureKeys();
    }

    public function testGetKeysFailsWhenTheKeySetCannotBeFetched()
    {
        $providerKeys = $this->createProviderKeys(new MockHttpClient(new MockResponse('', ['http_code' => 500])));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('JWKS could not be fetched');

        $providerKeys->getSignatureKeys();
    }

    public function testGetAnnouncedIssuerReturnsWhatTheDocumentCarries()
    {
        $this->assertSame('https://provider.example.com', $this->createProviderKeys()->getAnnouncedIssuer());
    }

    public function testGetAnnouncedIssuerReturnsNullWhenTheDocumentAnnouncesNone()
    {
        $providerKeys = $this->createProviderKeys(null, null, 3600, null, ['jwks_uri' => 'https://provider.example.com/jwks']);

        $this->assertNull($providerKeys->getAnnouncedIssuer());
    }

    public function testConstructorRejectsANegativeDefaultCacheTtl()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->createProviderKeys(null, null, -1);
    }

    /**
     * @param list<array<string, mixed>> $jwks
     */
    private function createProviderKeys(?HttpClientInterface $httpClient = null, ?CacheInterface $cache = null, int $defaultCacheTtl = 3600, ?array $jwks = null, ?array $configuration = null, ?MockClock $clock = null, bool $enforceKeyUsageVerification = true): OidcProviderKeys
    {
        $configuration ??= [
            'issuer' => 'https://provider.example.com',
            'jwks_uri' => 'https://provider.example.com/jwks',
        ];
        $jwks ??= [['keys' => [self::SIGNING_JWK]]];

        $discovery = new OidcDiscovery(
            new MockHttpClient(new JsonMockResponse($configuration)),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
        );

        return new OidcProviderKeys(
            $discovery,
            $httpClient ?? new MockHttpClient(array_map(static fn (array $jwkSet): JsonMockResponse => new JsonMockResponse($jwkSet), $jwks)),
            $cache ?? new ArrayAdapter(),
            $clock ?? new MockClock(),
            $defaultCacheTtl,
            $enforceKeyUsageVerification,
        );
    }
}

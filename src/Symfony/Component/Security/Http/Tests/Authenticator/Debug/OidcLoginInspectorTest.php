<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Debug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Debug\OidcLoginInspector;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableOidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClientInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class OidcLoginInspectorTest extends TestCase
{
    private const ISSUER = 'https://provider.example.com';
    private const DISCOVERY_URL = 'https://provider.example.com/.well-known/openid-configuration';
    private const DOCUMENT = [
        'issuer' => self::ISSUER,
        'authorization_endpoint' => 'https://provider.example.com/authorize',
        'token_endpoint' => 'https://provider.example.com/token',
        'userinfo_endpoint' => 'https://provider.example.com/userinfo',
        'jwks_uri' => 'https://provider.example.com/jwks',
        'end_session_endpoint' => 'https://provider.example.com/logout',
        'id_token_signing_alg_values_supported' => ['RS256', 'ES256'],
        'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported' => ['openid', 'profile', 'email'],
        'authorization_response_iss_parameter_supported' => true,
        'claims_supported' => ['sub', 'email'],
    ];
    private const CONFIG = [
        'provider_uri' => self::ISSUER,
        'client_id' => 'my-client',
        'scope' => ['openid', 'profile'],
        'pkce' => ['enabled' => true, 'method' => 'S256'],
        'user_data_source' => 'userinfo',
        'user_identifier_claim' => 'sub',
        'id_token_signature' => ['required' => true, 'algorithms' => ['RS256'], 'enforce_key_usage_verification' => true],
        'refresh_access_token' => ['enabled' => true, 'leeway' => 30],
        'enable_end_session' => true,
    ];
    private const ID_TOKEN = 'eyJhbGciOiJSUzI1NiIsImtpZCI6ImtleS0xIn0.eyJzdWIiOiJ1c2VyLTQyIiwiZXhwIjoxNzAwMDAwMDAwfQ.raw-signature';

    private MockClock $clock;

    protected function setUp(): void
    {
        // the cache pools expire their entries against the wall clock
        $this->clock = new MockClock();
    }

    public function testInspectReportsACachedDiscoveryDocumentWithoutContactingTheProvider()
    {
        $cache = new ArrayAdapter();
        $httpClient = new MockHttpClient(new JsonMockResponse(self::DOCUMENT));
        $this->createDiscovery($httpClient, $cache)->getConfiguration();
        $this->assertSame(1, $httpClient->getRequestsCount());

        // a fresh instance, the way the next request sees it, with a client that must stay silent
        $silent = new MockHttpClient(static fn () => throw new \LogicException('The provider must not be contacted.'));
        $discovery = new OidcDiscovery($silent, $cache, self::DISCOVERY_URL, self::ISSUER, 3600);

        $data = $this->createInspector($discovery)->inspect(null);

        $this->assertSame('main', $data['firewall']);
        $this->assertSame('cached', $data['discovery']['status']);
        $this->assertSame(self::DISCOVERY_URL, $data['discovery']['url']);
        $this->assertSame(self::ISSUER, $data['discovery']['issuer']);
        $this->assertSame('oidc_discovery.'.hash('xxh128', self::DISCOVERY_URL), $data['discovery']['cache_key']);
        $this->assertEqualsWithDelta(time() + 3600, $data['discovery']['expires_at'], 5);
        $this->assertEqualsWithDelta(3600, $data['discovery']['expires_in'], 5);
        $this->assertSame(self::DOCUMENT, $data['discovery']['document']);
        $this->assertSame(0, $silent->getRequestsCount());
        $this->assertSame([], $data['warnings']);
    }

    public function testInspectReportsAMissingDiscoveryDocumentWithoutFetchingIt()
    {
        $httpClient = new MockHttpClient(static fn () => throw new \LogicException('The provider must not be contacted.'));
        $discovery = $this->createDiscovery($httpClient, new ArrayAdapter());
        $verifier = new OidcSignatureVerifier($discovery, new ArrayAdapter(), $httpClient, ['RS256'], 3600, true, $this->clock);

        $data = $this->createInspector($discovery, $verifier)->inspect(null);

        $this->assertSame('not_cached', $data['discovery']['status']);
        $this->assertNull($data['discovery']['expires_at']);
        $this->assertNull($data['discovery']['document']);
        $this->assertSame(0, $httpClient->getRequestsCount());
        // without the document, the JWKS URI is unknown, and so is where the keys would be
        $this->assertSame('unknown', $data['jwks']['status']);
        $this->assertNull($data['jwks']['uri']);
        $this->assertContains('The discovery document is not cached: the next authentication fetches it from the provider first.', $data['warnings']);
    }

    public function testInspectReportsTheDocumentLoadedDuringTheRequestWhenThePoolCannotBeRead()
    {
        // a pool implementing the contracts only: it cannot be read without a callback
        $pool = $this->createStub(CacheInterface::class);
        $pool->method('get')->willReturnCallback(function (string $key, callable $callback): mixed {
            $save = true;

            return $callback($this->createStub(ItemInterface::class), $save);
        });
        $discovery = $this->createDiscovery(new MockHttpClient(new JsonMockResponse(self::DOCUMENT)), $pool);

        $this->assertSame('unknown', $this->createInspector($discovery)->inspect(null)['discovery']['status']);

        $discovery->getConfiguration();

        $data = $this->createInspector($discovery)->inspect(null);
        $this->assertSame('memoized', $data['discovery']['status']);
        $this->assertSame(self::DOCUMENT, $data['discovery']['document']);
    }

    public function testInspectReportsTheCachedSigningKeys()
    {
        $discovery = $this->createDiscovery(new MockHttpClient(new JsonMockResponse(self::DOCUMENT)), new ArrayAdapter());
        $discovery->getConfiguration();
        $jwksCache = new ArrayAdapter();
        $jwksCache->get('oidc_jwks.'.hash('xxh128', 'https://provider.example.com/jwks'), function (ItemInterface $item): array {
            $item->expiresAfter(600);

            return [
                'keys' => [
                    ['kid' => 'key-1', 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => 'public-modulus', 'e' => 'AQAB'],
                    ['kid' => 'key-2', 'kty' => 'EC', 'crv' => 'P-256', 'x' => 'x', 'y' => 'y'],
                ],
                'fetched_at' => $this->clock->now()->getTimestamp() - 60,
            ];
        });
        $verifier = new OidcSignatureVerifier($discovery, $jwksCache, new MockHttpClient(), ['RS256'], 3600, true, $this->clock);
        $token = $this->createToken(['oidc_id_token' => self::ID_TOKEN, 'oidc_access_token' => 'raw-access-token']);

        $data = $this->createInspector($discovery, $verifier)->inspect($token);

        $this->assertSame('cached', $data['jwks']['status']);
        $this->assertSame('https://provider.example.com/jwks', $data['jwks']['uri']);
        $this->assertSame(['RS256'], $data['jwks']['algorithms']);
        $this->assertTrue($data['jwks']['enforce_key_usage_verification']);
        $this->assertSame($this->clock->now()->getTimestamp() - 60, $data['jwks']['fetched_at']);
        $this->assertEqualsWithDelta(600, $data['jwks']['expires_in'], 5);
        $this->assertSame([
            ['kid' => 'key-1', 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'crv' => null, 'current' => true],
            ['kid' => 'key-2', 'kty' => 'EC', 'alg' => null, 'use' => null, 'crv' => 'P-256', 'current' => false],
        ], $data['jwks']['keys']);
        $this->assertStringNotContainsString('public-modulus', json_encode($data));
    }

    public function testInspectReportsTheSigningKeysAsNotCachedAndSkipsThemWithoutVerifier()
    {
        $discovery = $this->createDiscovery(new MockHttpClient(new JsonMockResponse(self::DOCUMENT)), new ArrayAdapter());
        $discovery->getConfiguration();
        $verifier = new OidcSignatureVerifier($discovery, new ArrayAdapter(), new MockHttpClient(), ['RS256'], 3600, false, $this->clock);

        $data = $this->createInspector($discovery, $verifier)->inspect(null);
        $this->assertSame('not_cached', $data['jwks']['status']);
        $this->assertSame('oidc_jwks.lax.'.hash('xxh128', 'https://provider.example.com/jwks'), $data['jwks']['cache_key']);
        $this->assertSame([], $data['jwks']['keys']);

        $this->assertNull($this->createInspector($discovery)->inspect(null)['jwks']);
    }

    public function testInspectDescribesTheTokensOfTheLoggedInUser()
    {
        $now = $this->clock->now()->getTimestamp();
        $token = $this->createToken([
            'oidc_id_token' => self::ID_TOKEN,
            'oidc_access_token' => 'raw-access-token',
            'oidc_access_token_type' => 'DPoP',
            'oidc_refresh_token' => 'raw-refresh-token',
            'oidc_access_token_expires_at' => $now + 20,
            'oidc_acr' => 'urn:example:gold',
            'oidc_amr' => ['pwd', 'otp'],
        ]);

        $data = $this->createInspector($this->createDiscovery(new MockHttpClient(), new ArrayAdapter()))->inspect($token);

        $tokens = $data['token'];
        $this->assertSame('jwt', $tokens['id_token']['format']);
        $this->assertSame('key-1', $tokens['id_token']['header']['kid']);
        $this->assertSame('user-42', $tokens['id_token']['claims']['sub']);
        $this->assertSame('opaque', $tokens['access_token']['format']);
        $this->assertSame('DPoP', $tokens['access_token_type']);
        $this->assertSame($now + 20, $tokens['access_token_expires_at']);
        $this->assertSame(20, $tokens['access_token_expires_in']);
        $this->assertSame('opaque', $tokens['refresh_token']['format']);
        $this->assertSame('urn:example:gold', $tokens['acr']);
        $this->assertSame(['pwd', 'otp'], $tokens['amr']);
        // 20 seconds left, 30 seconds of leeway: the next request renews it
        $this->assertSame(['enabled' => true, 'leeway' => 30, 'due' => true], $tokens['refresh']);
        $this->assertStringNotContainsString('raw-', json_encode($data));
        $this->assertStringNotContainsString(self::ID_TOKEN, json_encode($data));
    }

    public function testInspectReportsNoTokenWhenTheUserDidNotLogInWithOidc()
    {
        $discovery = $this->createDiscovery(new MockHttpClient(), new ArrayAdapter());
        $inspector = $this->createInspector($discovery);

        $this->assertNull($inspector->inspect(null)['token']);
        $this->assertNull($inspector->inspect($this->createToken([]))['token']);
    }

    public function testInspectRecordsTheCallsMadeToTheProvider()
    {
        $client = $this->createStub(OidcClientInterface::class);
        $client->method('getClientAuthenticationMethod')->willReturn('client_secret_basic');
        $client->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);
        $traceable = new TraceableOidcClient($client);
        $traceable->fetchUserInfo('raw-access-token');

        $data = $this->createInspector($this->createDiscovery(new MockHttpClient(), new ArrayAdapter()), null, $traceable)->inspect(null);

        $this->assertSame('client_secret_basic', $data['config']['client_authentication']);
        $this->assertCount(1, $data['calls']);
        $this->assertSame('userinfo', $data['calls'][0]['operation']);
    }

    #[DataProvider('provideWarnings')]
    public function testInspectWarnsAboutWeakOrMismatchingSettings(array $config, array $document, ?array $attributes, string $expectedWarning, string $clientAuthenticationMethod = 'client_secret_basic')
    {
        $cache = new ArrayAdapter();
        $discovery = $this->createDiscovery(new MockHttpClient(new JsonMockResponse($document)), $cache);
        $discovery->getConfiguration();
        $token = null !== $attributes ? $this->createToken($attributes) : null;

        $data = $this->createInspector($discovery, null, null, array_replace_recursive(self::CONFIG, $config), $clientAuthenticationMethod)->inspect($token);

        $this->assertContains($expectedWarning, $data['warnings'], implode("\n", $data['warnings']));
    }

    public static function provideWarnings(): iterable
    {
        yield 'signature not verified' => [['id_token_signature' => ['required' => false]], self::DOCUMENT, null, 'The ID token signature is not verified ("id_token_signature.required" is false): only the TLS verification of the token request ties the ID token to the provider.'];
        yield 'PKCE disabled' => [['pkce' => ['enabled' => false]], self::DOCUMENT, null, 'PKCE is disabled: nothing but the client secret binds the authorization code to this client.'];
        yield 'PKCE plain' => [['pkce' => ['method' => 'plain']], self::DOCUMENT, null, 'PKCE uses the "plain" method, which sends the code verifier as it is in the authorization request; "S256" is what RFC 7636 recommends.'];
        yield 'PKCE method not announced' => [['pkce' => ['method' => 'plain']], self::DOCUMENT, null, 'The provider does not announce the "plain" PKCE method: its "code_challenge_methods_supported" lists "S256".'];
        yield 'algorithm not announced' => [['id_token_signature' => ['algorithms' => ['PS256']]], self::DOCUMENT, null, 'None of the configured ID token signature algorithms ("PS256") is announced by the provider, whose "id_token_signing_alg_values_supported" lists "RS256", "ES256".'];
        yield 'client authentication not announced' => [[], ['token_endpoint_auth_methods_supported' => ['private_key_jwt']] + self::DOCUMENT, null, 'The provider does not announce the "client_secret_basic" client authentication method: its "token_endpoint_auth_methods_supported" lists "private_key_jwt".'];
        // OpenID Connect Discovery 1.0, Section 3: an absent list means "client_secret_basic"
        yield 'client authentication default' => [[], array_diff_key(self::DOCUMENT, ['token_endpoint_auth_methods_supported' => true]), null, 'The provider does not announce the "client_secret_post" client authentication method: its "token_endpoint_auth_methods_supported" lists "client_secret_basic".', 'client_secret_post'];
        yield 'no userinfo endpoint' => [[], array_diff_key(self::DOCUMENT, ['userinfo_endpoint' => true]), null, 'The user claims are read from the UserInfo endpoint, which the provider does not announce: set "user_data_source" to "id_token".'];
        yield 'no end_session endpoint' => [[], array_diff_key(self::DOCUMENT, ['end_session_endpoint' => true]), null, 'RP-Initiated Logout is enabled, but the provider announces no "end_session_endpoint": logouts stay local.'];
        yield 'no iss parameter' => [[], array_diff_key(self::DOCUMENT, ['authorization_response_iss_parameter_supported' => true]), null, 'The provider does not announce the "iss" authorization response parameter of RFC 9207: a callback cannot be tied to the provider that issued it.'];
        yield 'expired without refresh token' => [[], self::DOCUMENT, ['oidc_access_token' => 'raw', 'oidc_access_token_expires_at' => 1], 'The access token expired, and the security token holds no refresh token to renew it: the provider only issues one when asked for, e.g. with the "offline_access" scope.'];
        yield 'expired with refresh disabled' => [['refresh_access_token' => ['enabled' => false]], self::DOCUMENT, ['oidc_access_token' => 'raw', 'oidc_refresh_token' => 'raw', 'oidc_access_token_expires_at' => 1], 'The access token expired: enable "refresh_access_token" to renew it with the refresh token the security token holds.'];
    }

    public function testInspectNeverThrows()
    {
        $discovery = $this->createDiscovery(new MockHttpClient(), new ArrayAdapter());
        $client = $this->createStub(OidcClientInterface::class);
        $client->method('getClientAuthenticationMethod')->willThrowException(new \RuntimeException('Broken client authentication.'));
        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willThrowException(new \RuntimeException('Broken token.'));

        $data = $this->createInspector($discovery, null, new TraceableOidcClient($client))->inspect($token);

        $this->assertSame(['status' => 'error', 'error' => 'Broken token.'], $data['token']);
        $this->assertSame('unknown', $data['config']['client_authentication']);
        $this->assertSame('not_cached', $data['discovery']['status']);
    }

    private function createDiscovery(MockHttpClient $httpClient, CacheInterface $cache): OidcDiscovery
    {
        return new OidcDiscovery($httpClient, $cache, self::DISCOVERY_URL, self::ISSUER, 3600);
    }

    private function createInspector(OidcDiscovery $discovery, ?OidcSignatureVerifier $verifier = null, ?TraceableOidcClient $client = null, array $config = self::CONFIG, string $clientAuthenticationMethod = 'client_secret_post'): OidcLoginInspector
    {
        if (null === $client) {
            $inner = $this->createStub(OidcClientInterface::class);
            $inner->method('getClientAuthenticationMethod')->willReturn($clientAuthenticationMethod);
            $client = new TraceableOidcClient($inner);
        }

        return new OidcLoginInspector('main', $discovery, $verifier, $client, $config, $this->clock);
    }

    private function createToken(array $attributes): UsernamePasswordToken
    {
        $token = new UsernamePasswordToken(new InMemoryUser('user-42', null, ['ROLE_USER']), 'main', ['ROLE_USER']);
        $token->setAttributes($attributes);

        return $token;
    }
}

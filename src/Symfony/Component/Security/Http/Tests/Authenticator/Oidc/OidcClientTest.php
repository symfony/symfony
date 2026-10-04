<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Oidc;

use Jose\Component\Core\JWK;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientAuthenticationInterface;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretJwt;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretPost;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\NoClientAuthentication;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\SelfSignedTlsClientAuth;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\TlsClientAuth;
use Symfony\Component\Security\Http\OAuth2\Dpop\DpopProofFactory;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class OidcClientTest extends TestCase
{
    private const CERTIFICATE = ['local_cert' => '/path/to/client.pem', 'local_pk' => '/path/to/client.key', 'passphrase' => null];

    private const DPOP_JWK = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
    ];

    private OidcDiscovery $discovery;
    private HttpClientInterface $httpClient;

    /**
     * @var list<array<string, string>>
     */
    private array $sentHeaders = [];

    /**
     * @var list<?string>
     */
    private array $sentProofs = [];

    protected function setUp(): void
    {
        $this->discovery = $this->createDiscovery([
            'token_endpoint' => 'https://provider.example.com/token',
            'userinfo_endpoint' => 'https://provider.example.com/userinfo',
            'issuer' => 'https://provider.example.com',
        ]);

        $this->httpClient = $this->createMock(HttpClientInterface::class);
    }

    public function testExchangeCode()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'access-123',
            'id_token' => 'id-token-abc',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://provider.example.com/token', $this->callback(function (array $options) {
                $this->assertSame('authorization_code', $options['body']['grant_type']);
                $this->assertSame('auth-code', $options['body']['code']);
                $this->assertSame('https://app.example.com/callback', $options['body']['redirect_uri']);
                $this->assertSame('test-client-id', $options['body']['client_id']);
                $this->assertSame(0, $options['max_redirects']);

                return true;
            }))
            ->willReturn($response);

        $tokens = $this->createClient()->exchangeCode('auth-code', 'https://app.example.com/callback');

        $this->assertSame('access-123', $tokens['access_token']);
        $this->assertSame('id-token-abc', $tokens['id_token']);
    }

    public function testExchangeCodeWithCodeVerifier()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['access_token' => 'access-123']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://provider.example.com/token', $this->callback(function (array $options) {
                $this->assertSame('my-code-verifier', $options['body']['code_verifier']);

                return true;
            }))
            ->willReturn($response);

        $this->createClient()->exchangeCode('auth-code', 'https://app.example.com/callback', 'my-code-verifier');
    }

    public function testTheTokenRequestIsMadeWithTheOptionsTheClientAuthenticationReturns()
    {
        $clientAuthentication = $this->createMock(ClientAuthenticationInterface::class);
        $clientAuthentication->expects($this->once())
            ->method('authenticate')
            ->with('test-client-id', 'https://provider.example.com/token', $this->anything())
            ->willReturn(['body' => ['signed' => 'assertion'], 'headers' => ['X-Client: yes']]);

        $mockResponse = new JsonMockResponse(['access_token' => 'access-123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->discovery, 'test-client-id', $clientAuthentication);
        $client->exchangeCode('auth-code', 'https://app.example.com/callback');

        $requestOptions = $mockResponse->getRequestOptions();
        $this->assertSame('signed=assertion', $requestOptions['body']);
        $this->assertContains('X-Client: yes', $requestOptions['headers']);
    }

    public function testGetClientAuthenticationMethodReportsTheMethodOfItsClientAuthentication()
    {
        $this->assertSame('client_secret_post', $this->createClient()->getClientAuthenticationMethod());
        $this->assertSame('none', $this->createClient(new NoClientAuthentication())->getClientAuthenticationMethod());
    }

    public function testExchangeCodeThrowsWhenEndpointMissing()
    {
        $client = $this->createClient(discovery: $this->createDiscovery(['userinfo_endpoint' => 'https://provider.example.com/userinfo']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not announce any "token_endpoint"');

        $client->exchangeCode('auth-code', 'https://app.example.com/callback');
    }

    /**
     * The client secret and the tokens travel through the token endpoint.
     *
     * A discovery document announcing a plain HTTP endpoint takes their confidentiality away.
     */
    public function testExchangeCodeRejectsAnInsecureTokenEndpoint()
    {
        $this->httpClient->expects($this->never())->method('request');

        $client = $this->createClient(discovery: $this->createDiscovery(['token_endpoint' => 'http://provider.example.com/token']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $client->exchangeCode('auth-code', 'https://app.example.com/callback');
    }

    public function testExchangeCodeConvertsTransportErrorsToAuthenticationException()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException(new TransportException('Connection refused.'));
        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The OIDC token endpoint request failed');

        $this->createClient()->exchangeCode('auth-code', 'https://app.example.com/callback');
    }

    /**
     * The UserInfo endpoint is a protected resource.
     *
     * It takes the access token as a bearer credential, so the client authentication has no
     * say in that request.
     */
    public function testFetchUserInfoUsesTheAccessTokenAndNoClientAuthentication()
    {
        $clientAuthentication = $this->createMock(ClientAuthenticationInterface::class);
        $clientAuthentication->expects($this->never())->method('authenticate');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['sub' => '123', 'email' => 'test@example.com']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://provider.example.com/userinfo', ['auth_bearer' => 'access-token', 'max_redirects' => 0])
            ->willReturn($response);

        $claims = $this->createClient($clientAuthentication)->fetchUserInfo('access-token');

        $this->assertSame('123', $claims['sub']);
        $this->assertSame('test@example.com', $claims['email']);
    }

    public function testFetchUserInfoThrowsWhenEndpointMissing()
    {
        $client = $this->createClient(discovery: $this->createDiscovery(['token_endpoint' => 'https://provider.example.com/token']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not announce any "userinfo_endpoint"');

        $client->fetchUserInfo('access-token');
    }

    public function testFetchUserInfoRejectsAnInsecureUserInfoEndpoint()
    {
        $this->httpClient->expects($this->never())->method('request');

        $client = $this->createClient(discovery: $this->createDiscovery(['userinfo_endpoint' => 'http://provider.example.com/userinfo']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $client->fetchUserInfo('access-token');
    }

    public function testFetchUserInfoConvertsTransportErrorsToAuthenticationException()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException(new TransportException('Connection refused.'));
        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The OIDC userinfo endpoint request failed');

        $this->createClient()->fetchUserInfo('access-token');
    }

    public function testRefreshToken()
    {
        $mockResponse = new JsonMockResponse([
            'access_token' => 'access-456',
            'refresh_token' => 'refresh-456',
            'expires_in' => 300,
        ]);

        $client = new OidcClient(new MockHttpClient($mockResponse), $this->discovery, 'test-client-id', new ClientSecretPost('test-client-secret'));
        $tokens = $client->refreshToken('refresh-123');

        $this->assertSame('https://provider.example.com/token', $mockResponse->getRequestUrl());
        parse_str($mockResponse->getRequestOptions()['body'], $body);
        $this->assertSame('refresh_token', $body['grant_type']);
        $this->assertSame('refresh-123', $body['refresh_token']);
        $this->assertSame('test-client-id', $body['client_id']);
        $this->assertSame('test-client-secret', $body['client_secret']);
        $this->assertArrayNotHasKey('scope', $body);
        $this->assertSame('access-456', $tokens['access_token']);
        $this->assertSame('refresh-456', $tokens['refresh_token']);
        $this->assertSame(0, $mockResponse->getRequestOptions()['max_redirects']);
    }

    public function testTheRefreshRequestIsMadeWithTheOptionsTheClientAuthenticationReturns()
    {
        $clientAuthentication = $this->createMock(ClientAuthenticationInterface::class);
        $clientAuthentication->expects($this->once())
            ->method('authenticate')
            ->with('test-client-id', 'https://provider.example.com/token', $this->anything())
            ->willReturn(['body' => ['grant_type' => 'refresh_token'], 'auth_basic' => 'test-client-id:test-client-secret']);

        $mockResponse = new JsonMockResponse(['access_token' => 'access-456']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->discovery, 'test-client-id', $clientAuthentication);
        $client->refreshToken('refresh-123');

        $requestOptions = $mockResponse->getRequestOptions();
        $this->assertSame('grant_type=refresh_token', $requestOptions['body']);
        $this->assertSame(['Authorization: Basic '.base64_encode('test-client-id:test-client-secret')], $requestOptions['normalized_headers']['authorization']);
    }

    public function testRefreshTokenNarrowsTheScopes()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-456']);

        $client = new OidcClient(new MockHttpClient($mockResponse), $this->discovery, 'test-client-id', new ClientSecretPost('test-client-secret'));
        $client->refreshToken('refresh-123', ['openid', 'profile']);

        parse_str($mockResponse->getRequestOptions()['body'], $body);
        $this->assertSame('openid profile', $body['scope']);
    }

    /**
     * Only "invalid_grant" says the refresh token is gone for good.
     *
     * RFC 6749, Section 5.2 makes it the only failure a caller may act on by ending the
     * session.
     */
    public function testRefreshTokenReportsAnInvalidGrantOnItsOwn()
    {
        $client = new OidcClient(
            new MockHttpClient(new JsonMockResponse(['error' => 'invalid_grant', 'error_description' => 'Token is not active'], ['http_code' => 400])),
            $this->discovery,
            'test-client-id',
            new ClientSecretPost('test-client-secret'),
        );

        $this->expectException(OidcInvalidGrantException::class);
        $this->expectExceptionMessage('The OIDC provider rejected the refresh token');

        $client->refreshToken('refresh-123');
    }

    public function testRefreshTokenReportsAnotherProviderErrorAsAPlainFailure()
    {
        $client = new OidcClient(
            new MockHttpClient(new JsonMockResponse(['error' => 'invalid_client'], ['http_code' => 401])),
            $this->discovery,
            'test-client-id',
            new ClientSecretPost('test-client-secret'),
        );

        try {
            $client->refreshToken('refresh-123');
            $this->fail(\sprintf('Expected an "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException $e) {
            $this->assertNotInstanceOf(OidcInvalidGrantException::class, $e);
            $this->assertStringContainsString('The OIDC token endpoint request failed', $e->getMessage());
        }
    }

    public function testRefreshTokenReportsAnUnreachableProviderAsAPlainFailure()
    {
        $client = new OidcClient(
            new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503])),
            $this->discovery,
            'test-client-id',
            new ClientSecretPost('test-client-secret'),
        );

        try {
            $client->refreshToken('refresh-123');
            $this->fail(\sprintf('Expected an "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException $e) {
            $this->assertNotInstanceOf(OidcInvalidGrantException::class, $e);
            $this->assertStringContainsString('The OIDC token endpoint request failed', $e->getMessage());
        }
    }

    public function testRefreshTokenRejectsAnInsecureTokenEndpoint()
    {
        $this->httpClient->expects($this->never())->method('request');

        $client = $this->createClient(discovery: $this->createDiscovery(['token_endpoint' => 'http://provider.example.com/token']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $client->refreshToken('refresh-123');
    }

    public function testTheTokenRequestIsMadeToTheMtlsAlias()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->createMtlsDiscovery(), 'test-client-id', new TlsClientAuth(), self::CERTIFICATE);

        $client->exchangeCode('auth-code', 'https://app.example.com/callback');

        $this->assertSame('https://mtls.provider.example.com/token', $mockResponse->getRequestUrl());
    }

    public function testTheRefreshRequestIsMadeToTheMtlsAlias()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-456']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->createMtlsDiscovery(), 'test-client-id', new SelfSignedTlsClientAuth(), self::CERTIFICATE);

        $client->refreshToken('refresh-123');

        $this->assertSame('https://mtls.provider.example.com/token', $mockResponse->getRequestUrl());
    }

    public function testTheTokenRequestOfAClientAuthenticatingWithASecretIsMadeToTheMtlsAlias()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->createMtlsDiscovery(), 'test-client-id', new ClientSecretPost('test-client-secret'), self::CERTIFICATE);

        $client->exchangeCode('auth-code', 'https://app.example.com/callback');

        $this->assertSame('https://mtls.provider.example.com/token', $mockResponse->getRequestUrl());
    }

    public function testTheTokenRequestOfAClientAuthenticationOfItsOwnIsMadeToTheMtlsAlias()
    {
        $clientAuthentication = new class implements ClientAuthenticationInterface {
            public function getMethod(): string
            {
                return 'tls_client_auth';
            }

            public function authenticate(string $clientId, string $tokenEndpoint, array $options): array
            {
                return $options;
            }
        };

        $mockResponse = new JsonMockResponse(['access_token' => 'access-123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->createMtlsDiscovery(), 'test-client-id', $clientAuthentication, self::CERTIFICATE);

        $client->exchangeCode('auth-code', 'https://app.example.com/callback');

        $this->assertSame('https://mtls.provider.example.com/token', $mockResponse->getRequestUrl());
    }

    public function testFetchUserInfoIsMadeToTheMtlsAlias()
    {
        $mockResponse = new JsonMockResponse(['sub' => '123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->createMtlsDiscovery(), 'test-client-id', new TlsClientAuth(), self::CERTIFICATE);

        $claims = $client->fetchUserInfo('access-token');

        $this->assertSame('123', $claims['sub']);
        $this->assertSame('https://mtls.provider.example.com/userinfo', $mockResponse->getRequestUrl());
        $this->assertSame(['Authorization: Bearer access-token'], $mockResponse->getRequestOptions()['normalized_headers']['authorization']);
    }

    public function testFetchUserInfoIsMadeToTheAnnouncedEndpointWhenItHasNoMtlsAlias()
    {
        $mockResponse = new JsonMockResponse(['sub' => '123']);
        $discovery = $this->createDiscovery([
            'issuer' => 'https://provider.example.com',
            'token_endpoint' => 'https://provider.example.com/token',
            'userinfo_endpoint' => 'https://provider.example.com/userinfo',
            'mtls_endpoint_aliases' => ['token_endpoint' => 'https://mtls.provider.example.com/token'],
        ]);
        $client = new OidcClient(new MockHttpClient($mockResponse), $discovery, 'test-client-id', new TlsClientAuth(), self::CERTIFICATE);

        $client->fetchUserInfo('access-token');

        $this->assertSame('https://provider.example.com/userinfo', $mockResponse->getRequestUrl());
        $this->assertSame('/path/to/client.pem', $mockResponse->getRequestOptions()['local_cert'], 'the endpoint having no alias is no reason to drop the certificate');
    }

    public function testTheMtlsAliasesAreIgnoredByAClientPresentingNoCertificate()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->createMtlsDiscovery(), 'test-client-id', new ClientSecretPost('test-client-secret'));

        $client->exchangeCode('auth-code', 'https://app.example.com/callback');

        $this->assertSame('https://provider.example.com/token', $mockResponse->getRequestUrl());
    }

    #[DataProvider('provideUnusableMtlsAliases')]
    public function testAnUnusableMtlsAliasIsRefusedBeforeAnyRequest(\Closure $request, string $endpoint, mixed $alias)
    {
        $httpClient = new MockHttpClient();
        $discovery = $this->createDiscovery([
            'issuer' => 'https://provider.example.com',
            'token_endpoint' => 'https://provider.example.com/token',
            'userinfo_endpoint' => 'https://provider.example.com/userinfo',
            'mtls_endpoint_aliases' => [$endpoint => $alias],
        ]);
        $client = new OidcClient($httpClient, $discovery, 'test-client-id', new TlsClientAuth(), self::CERTIFICATE);

        try {
            $request($client);
            $this->fail(\sprintf('Expected an "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException $e) {
            $this->assertSame(\sprintf('The "mtls_endpoint_aliases.%s" announced by the OIDC provider must be an HTTPS URL.', $endpoint), $e->getMessage());
        }

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public static function provideUnusableMtlsAliases(): iterable
    {
        $exchangeCode = static fn (OidcClient $client) => $client->exchangeCode('auth-code', 'https://app.example.com/callback');
        $refreshToken = static fn (OidcClient $client) => $client->refreshToken('refresh-123');
        $fetchUserInfo = static fn (OidcClient $client) => $client->fetchUserInfo('access-token');

        yield 'token, plain HTTP' => [$exchangeCode, 'token_endpoint', 'http://mtls.provider.example.com/token'];
        yield 'token, empty' => [$exchangeCode, 'token_endpoint', ''];
        yield 'token, null' => [$exchangeCode, 'token_endpoint', null];
        yield 'refresh, plain HTTP' => [$refreshToken, 'token_endpoint', 'http://mtls.provider.example.com/token'];
        yield 'userinfo, plain HTTP' => [$fetchUserInfo, 'userinfo_endpoint', 'http://mtls.provider.example.com/userinfo'];
        yield 'userinfo, null' => [$fetchUserInfo, 'userinfo_endpoint', null];
    }

    public function testNoClientCertificateIsAddedToTheRequestOfAClientHoldingNone()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->createMtlsDiscovery(), 'test-client-id', new TlsClientAuth());

        $client->exchangeCode('auth-code', 'https://app.example.com/callback');

        $this->assertArrayNotHasKey('local_cert', array_filter($mockResponse->getRequestOptions(), static fn ($value): bool => null !== $value));
    }

    #[DataProvider('provideRequestsToTheProvider')]
    public function testTheCertificateIsPresentedOnEveryRequestToTheProvider(\Closure $request)
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-123', 'sub' => '123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->createMtlsDiscovery(), 'test-client-id', new TlsClientAuth(), self::CERTIFICATE);

        $request($client);

        $options = $mockResponse->getRequestOptions();
        $this->assertSame('/path/to/client.pem', $options['local_cert']);
        $this->assertSame('/path/to/client.key', $options['local_pk']);
    }

    public static function provideRequestsToTheProvider(): iterable
    {
        yield 'token' => [static fn (OidcClient $client) => $client->exchangeCode('auth-code', 'https://app.example.com/callback')];
        yield 'refresh' => [static fn (OidcClient $client) => $client->refreshToken('refresh-123')];
        yield 'userinfo' => [static fn (OidcClient $client) => $client->fetchUserInfo('access-token')];
    }

    public function testTheCertificateWinsOverTheOptionsOfAScopedHttpClient()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-123']);
        $httpClient = new ScopingHttpClient(new MockHttpClient($mockResponse), [
            'https://mtls\.provider\.example\.com/' => ['local_cert' => '/path/to/another.pem'],
        ]);
        $client = new OidcClient($httpClient, $this->createMtlsDiscovery(), 'test-client-id', new TlsClientAuth(), self::CERTIFICATE);

        $client->exchangeCode('auth-code', 'https://app.example.com/callback');

        $this->assertSame('/path/to/client.pem', $mockResponse->getRequestOptions()['local_cert']);
    }

    private function createMtlsDiscovery(): OidcDiscovery
    {
        return $this->createDiscovery([
            'issuer' => 'https://provider.example.com',
            'token_endpoint' => 'https://provider.example.com/token',
            'userinfo_endpoint' => 'https://provider.example.com/userinfo',
            'mtls_endpoint_aliases' => [
                'token_endpoint' => 'https://mtls.provider.example.com/token',
                'userinfo_endpoint' => 'https://mtls.provider.example.com/userinfo',
            ],
        ]);
    }

    private function createClient(?ClientAuthenticationInterface $clientAuthentication = null, ?OidcDiscovery $discovery = null): OidcClient
    {
        return new OidcClient(
            $this->httpClient,
            $discovery ?? $this->discovery,
            'test-client-id',
            $clientAuthentication ?? new ClientSecretPost('test-client-secret'),
        );
    }

    /**
     * RFC 9449, Section 5: the token request carries a proof, and what comes back is bound
     * to the key it names.
     */
    public function testATokenRequestCarriesAProofOfTheKey()
    {
        $client = $this->createDpopClient(new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'DPoP']));

        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');

        $proof = self::decodePayload($this->sentProofs[0]);
        $this->assertSame('POST', $proof['htm']);
        $this->assertSame('https://provider.example.com/token', $proof['htu']);
        $this->assertArrayNotHasKey('nonce', $proof);
    }

    public function testARefreshCarriesAProofOfTheKey()
    {
        $client = $this->createDpopClient(new JsonMockResponse(['access_token' => 'access-456', 'token_type' => 'DPoP']));

        $client->refreshToken('refresh-123');

        $this->assertSame('https://provider.example.com/token', self::decodePayload($this->sentProofs[0])['htu']);
    }

    /**
     * Section 7.1: a bound token is presented under the "DPoP" scheme, and the proof names it.
     */
    public function testUserInfoPresentsTheTokenUnderTheDpopSchemeWithItsDigest()
    {
        $client = $this->createDpopClient(new JsonMockResponse(['sub' => 'user-1']));

        $client->fetchUserInfo('access-123');

        $this->assertSame('DPoP access-123', $this->sentHeaders[0]['Authorization']);
        $proof = self::decodePayload($this->sentProofs[0]);
        $this->assertSame('GET', $proof['htm']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', 'access-123', true)), '+/', '-_'), '='), $proof['ath']);
    }

    public function testUserInfoStaysABearerTokenWithoutDpop()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['sub' => 'user-1']);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://provider.example.com/userinfo', $this->callback(function (array $options): bool {
                $this->assertSame('access-123', $options['auth_bearer']);
                $this->assertArrayNotHasKey('headers', $options);

                return true;
            }))
            ->willReturn($response);

        $client = new OidcClient($this->httpClient, $this->discovery, 'client-id', new NoClientAuthentication());

        $this->assertSame(['sub' => 'user-1'], $client->fetchUserInfo('access-123'));
    }

    /**
     * Section 8: a provider may refuse a proof until it carries a nonce of its own.
     */
    public function testARequestRefusedForWantOfANonceIsSentAgainWithIt()
    {
        $client = $this->createDpopClient(
            new JsonMockResponse(['error' => 'use_dpop_nonce'], ['http_code' => 400, 'response_headers' => ['DPoP-Nonce' => 'nonce-1']]),
            new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'DPoP']),
        );

        $tokens = $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');

        $this->assertSame('access-123', $tokens['access_token']);
        $this->assertCount(2, $this->sentProofs);
        $this->assertArrayNotHasKey('nonce', self::decodePayload($this->sentProofs[0]));
        $this->assertSame('nonce-1', self::decodePayload($this->sentProofs[1])['nonce']);
    }

    /**
     * Section 9: a resource server asks for a nonce in its challenge rather than in a JSON body.
     */
    public function testAUserInfoRequestRefusedForWantOfANonceIsSentAgainWithIt()
    {
        $client = $this->createDpopClient(
            new MockResponse('', ['http_code' => 401, 'response_headers' => ['WWW-Authenticate' => 'DPoP error="use_dpop_nonce", error_description="Resource server requires nonce in DPoP proof"', 'DPoP-Nonce' => 'nonce-1']]),
            new JsonMockResponse(['sub' => 'user-1']),
        );

        $claims = $client->fetchUserInfo('access-123');

        $this->assertSame(['sub' => 'user-1'], $claims);
        $this->assertCount(2, $this->sentProofs);
        $this->assertArrayNotHasKey('nonce', self::decodePayload($this->sentProofs[0]));
        $this->assertSame('nonce-1', self::decodePayload($this->sentProofs[1])['nonce']);
    }

    /**
     * A client assertion carries a "jti" the provider remembers, so the retry is a new request.
     */
    public function testTheRetryAuthenticatesTheClientAgain()
    {
        $assertions = [];
        $clientAuthentication = $this->createStub(ClientAuthenticationInterface::class);
        $clientAuthentication->method('getMethod')->willReturn('private_key_jwt');
        $clientAuthentication->method('authenticate')->willReturnCallback(static function (string $clientId, string $endpoint, array $options) use (&$assertions): array {
            $options['body']['client_assertion'] = 'assertion-'.\count($assertions);
            $assertions[] = $options['body']['client_assertion'];

            return $options;
        });

        $client = $this->createDpopClient(
            new JsonMockResponse(['error' => 'use_dpop_nonce'], ['http_code' => 400, 'response_headers' => ['DPoP-Nonce' => 'nonce-1']]),
            new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'DPoP']),
            clientAuthentication: $clientAuthentication,
        );

        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');

        $this->assertSame(['assertion-0', 'assertion-1'], $assertions);
    }

    /**
     * A provider that keeps refusing is not retried forever: the caller reads the refusal.
     */
    public function testARequestIsNotSentAgainWhenTheProviderNamesNoNewNonce()
    {
        $client = $this->createDpopClient(
            new JsonMockResponse(['error' => 'use_dpop_nonce'], ['http_code' => 400]),
        );

        $this->expectException(AuthenticationException::class);

        try {
            $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');
        } finally {
            $this->assertCount(1, $this->sentProofs);
        }
    }

    /**
     * The nonce the provider last named is kept for the requests that follow.
     */
    public function testTheNonceIsKeptForTheNextRequest()
    {
        $client = $this->createDpopClient(
            new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'DPoP'], ['response_headers' => ['DPoP-Nonce' => 'nonce-1']]),
            new JsonMockResponse(['sub' => 'user-1']),
        );

        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');
        $client->fetchUserInfo('access-123');

        $this->assertArrayNotHasKey('nonce', self::decodePayload($this->sentProofs[0]));
        $this->assertSame('nonce-1', self::decodePayload($this->sentProofs[1])['nonce']);
    }

    /**
     * RFC 9449, Section 5: a provider "MAY elect to issue access tokens that are not DPoP
     * bound, which is signaled to the client with a value of Bearer in the token_type".
     *
     * "The client MUST discard the response in this case if this protection is deemed
     * important for the security of the application", and holding a key to bind the token to
     * is what deems it important: the unbound token is refused instead of being used.
     */
    public function testATokenTheProviderDidNotBindIsRefused()
    {
        $client = $this->createDpopClient(new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'Bearer']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('it did not bind the access token to the key of this client (RFC 9449, Section 5)');

        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');
    }

    public function testATokenResponseNamingNoTypeIsRefusedWhenTheTokenIsToBeBound()
    {
        $client = $this->createDpopClient(new JsonMockResponse(['access_token' => 'access-123']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('a "token_type" of "null"');

        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');
    }

    public function testARefreshedTokenTheProviderDidNotBindIsRefused()
    {
        $client = $this->createDpopClient(new JsonMockResponse(['access_token' => 'access-456', 'token_type' => 'Bearer']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('it did not bind the access token to the key of this client');

        $client->refreshToken('refresh-123');
    }

    /**
     * The type is compared as RFC 6749, Section 5.1 defines it, case insensitively.
     */
    public function testTheTokenTypeIsReadWhateverItsCase()
    {
        $client = $this->createDpopClient(new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'dpop']));

        $tokens = $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');

        $this->assertSame('access-123', $tokens['access_token']);
    }

    /**
     * RFC 6749, Section 7.1: "The client MUST NOT use an access token if it does not
     * understand the token type.".
     *
     * A client presenting bearer tokens cannot use a bound one, and sending it as a bearer
     * token is what RFC 9449, Section 7.2 has the resource server reject.
     */
    public function testAClientPresentingBearerTokensRefusesATokenOfAnotherType()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'DPoP']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->discovery, 'client-id', new NoClientAuthentication());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('issued an access token of the "DPoP" type, which this client cannot use');

        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');
    }

    /**
     * A provider leaving "token_type" out is handing out a token to be used as a bearer
     * token, which is what clients have always done with it.
     */
    public function testAClientPresentingBearerTokensAcceptsAResponseNamingNoType()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-123']);
        $client = new OidcClient(new MockHttpClient($mockResponse), $this->discovery, 'client-id', new NoClientAuthentication());

        $tokens = $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');

        $this->assertSame('access-123', $tokens['access_token']);
    }

    /**
     * RFC 9449, Section 9: "nonces will be only accepted by the server that issued them".
     *
     * The provider and a protected resource are not the same server, so the nonce of one is
     * never sent to the other: it would be refused, and cost a round trip every time the
     * client alternates between them.
     */
    public function testTheNonceOfOneServerIsNotSentToAnother()
    {
        $discovery = $this->createDiscovery([
            'issuer' => 'https://provider.example.com',
            'token_endpoint' => 'https://provider.example.com/token',
            'userinfo_endpoint' => 'https://resource.example.com/userinfo',
        ]);
        $responses = [
            new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'DPoP'], ['response_headers' => ['DPoP-Nonce' => 'provider-nonce']]),
            new JsonMockResponse(['access_token' => 'access-456', 'token_type' => 'DPoP']),
            new JsonMockResponse(['sub' => 'user-1']),
        ];
        $client = $this->createDpopClient($responses, discovery: $discovery);

        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');
        $client->refreshToken('refresh-123');
        $client->fetchUserInfo('access-456');

        $this->assertArrayNotHasKey('nonce', self::decodePayload($this->sentProofs[0]));
        $this->assertSame('provider-nonce', self::decodePayload($this->sentProofs[1])['nonce']);
        $this->assertArrayNotHasKey('nonce', self::decodePayload($this->sentProofs[2]));
    }

    public function testRequestPostsAClientAuthenticatedFormToTheAnnouncedEndpoint()
    {
        $discovery = $this->createDiscovery([
            'token_endpoint' => 'https://provider.example.com/token',
            'revocation_endpoint' => 'https://provider.example.com/revoke',
        ]);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://provider.example.com/revoke', $this->callback(function (array $options): bool {
                $this->assertSame(['client_id' => 'test-client-id', 'token' => 'a-refresh-token', 'token_type_hint' => 'refresh_token', 'client_secret' => 'test-client-secret'], $options['body']);
                $this->assertSame(0, $options['max_redirects']);

                return true;
            }))
            ->willReturn(new MockResponse('', ['http_code' => 200]));

        $response = $this->createClient(null, $discovery)->request('revocation_endpoint', ['token' => 'a-refresh-token', 'token_type_hint' => 'refresh_token']);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestThrowsWhenTheProviderAnnouncesNoSuchEndpoint()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not announce any "revocation_endpoint"');

        $this->createClient()->request('revocation_endpoint', ['token' => 'a-refresh-token']);
    }

    public function testRequestRejectsAnInsecureEndpoint()
    {
        $discovery = $this->createDiscovery(['token_endpoint' => 'https://provider.example.com/token', 'revocation_endpoint' => 'http://provider.example.com/revoke']);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $this->createClient(null, $discovery)->request('revocation_endpoint', ['token' => 'a-refresh-token']);
    }

    /**
     * RFC 9449, Section 5 carries a proof on a token request and on no other.
     */
    public function testRequestCarriesNoProofOutsideTheTokenEndpoint()
    {
        $discovery = $this->createDiscovery([
            'token_endpoint' => 'https://provider.example.com/token',
            'revocation_endpoint' => 'https://provider.example.com/revoke',
        ]);
        $client = $this->createDpopClient([new MockResponse('', ['http_code' => 200]), new JsonMockResponse(['access_token' => 'access-123', 'token_type' => 'DPoP'])], null, new ClientSecretPost('test-client-secret'), $discovery);

        $client->request('revocation_endpoint', ['token' => 'a-refresh-token']);
        $this->assertNull($this->sentProofs[0]);

        $client->request('token_endpoint', ['grant_type' => 'client_credentials']);
        $this->assertNotNull($this->sentProofs[1]);
    }

    public function testRequestIsMadeToTheMutualTlsAliasOfTheEndpoint()
    {
        $discovery = $this->createDiscovery([
            'token_endpoint' => 'https://provider.example.com/token',
            'revocation_endpoint' => 'https://provider.example.com/revoke',
            'mtls_endpoint_aliases' => ['revocation_endpoint' => 'https://mtls.provider.example.com/revoke'],
        ]);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://mtls.provider.example.com/revoke', $this->anything())
            ->willReturn(new MockResponse('', ['http_code' => 200]));

        $client = new OidcClient($this->httpClient, $discovery, 'test-client-id', new TlsClientAuth(), self::CERTIFICATE);

        $client->request('revocation_endpoint', ['token' => 'a-refresh-token']);
    }

    /**
     * RFC 9701, Section 4: a signed introspection response is only served to a request
     * announcing it.
     */
    public function testRequestIsMadeWithTheOptionsItIsGiven()
    {
        $discovery = $this->createDiscovery(['token_endpoint' => 'https://provider.example.com/token', 'introspection_endpoint' => 'https://provider.example.com/introspect']);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://provider.example.com/introspect', $this->callback(function (array $options): bool {
                $this->assertSame(['Accept' => 'application/token-introspection+jwt'], $options['headers']);

                return true;
            }))
            ->willReturn(new JsonMockResponse(['active' => true]));

        $this->createClient(null, $discovery)->request('introspection_endpoint', ['token' => 'a-token'], ['headers' => ['Accept' => 'application/token-introspection+jwt']]);
    }

    public function testRequestKeepsItsOwnBodyOverTheOneTheOptionsCarry()
    {
        $discovery = $this->createDiscovery(['token_endpoint' => 'https://provider.example.com/token', 'revocation_endpoint' => 'https://provider.example.com/revoke']);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://provider.example.com/revoke', $this->callback(function (array $options): bool {
                $this->assertSame(['client_id' => 'test-client-id', 'token' => 'a-token', 'client_secret' => 'test-client-secret'], $options['body']);
                $this->assertSame(0, $options['max_redirects']);

                return true;
            }))
            ->willReturn(new MockResponse('', ['http_code' => 200]));

        $this->createClient(null, $discovery)->request('revocation_endpoint', ['token' => 'a-token'], ['body' => ['token' => 'another-token'], 'max_redirects' => 5]);
    }

    /**
     * RFC 9449, Section 10.1: a pushed authorization request may carry a proof.
     */
    public function testRequestCarriesAProofToThePushedAuthorizationRequestEndpoint()
    {
        $discovery = $this->createDiscovery(['token_endpoint' => 'https://provider.example.com/token', 'pushed_authorization_request_endpoint' => 'https://provider.example.com/par']);
        $client = $this->createDpopClient(new JsonMockResponse(['request_uri' => 'urn:ietf:params:oauth:request_uri:x', 'expires_in' => 60]), null, new ClientSecretPost('test-client-secret'), $discovery);

        $client->request('pushed_authorization_request_endpoint', ['response_type' => 'code']);

        $this->assertNotNull($this->sentProofs[0]);
    }

    /**
     * A public client holds the token it revokes and the device code it redeems.
     */
    public function testAPublicClientReachesAnEndpointThatRedeemsNoAuthorizationCode()
    {
        $discovery = $this->createDiscovery([
            'token_endpoint' => 'https://provider.example.com/token',
            'revocation_endpoint' => 'https://provider.example.com/revoke',
        ]);
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturn(new MockResponse('', ['http_code' => 200]), new JsonMockResponse(['access_token' => 'access-123']));

        $client = $this->createClient(new NoClientAuthentication(), $discovery);

        $client->request('revocation_endpoint', ['token' => 'refresh-123', 'token_type_hint' => 'refresh_token']);
        $client->request('token_endpoint', ['grant_type' => 'urn:ietf:params:oauth:grant-type:device_code', 'device_code' => 'device-123']);
    }

    /**
     * Keyed on the endpoint being called, a revocation would name the revocation URL as its
     * audience, which a provider checking it against its token endpoint refuses.
     */
    public function testTheAssertionOfARequestNamesTheTokenEndpointWhateverIsRequested()
    {
        $discovery = $this->createDiscovery([
            'token_endpoint' => 'https://provider.example.com/token',
            'revocation_endpoint' => 'https://provider.example.com/revoke',
        ]);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://provider.example.com/revoke', $this->callback(function (array $options): bool {
                $this->assertSame('https://provider.example.com/token', self::decodePayload($options['body']['client_assertion'])['aud']);

                return true;
            }))
            ->willReturn(new MockResponse('', ['http_code' => 200]));

        // no discovery given to the assertion, which is the "audience: token_endpoint" option
        $client = $this->createClient(new ClientSecretJwt('a-client-secret-long-enough-for-hs256', 'HS256', 60, new MockClock()), $discovery);

        $client->request('revocation_endpoint', ['token' => 'a-token']);
    }

    /**
     * @param list<MockResponse>|MockResponse $responses The answers to the requests the client makes, in order
     */
    private function createDpopClient(array|MockResponse $responses, ?MockResponse $then = null, ?ClientAuthenticationInterface $clientAuthentication = null, ?OidcDiscovery $discovery = null): OidcClient
    {
        $responses = \is_array($responses) ? $responses : (null === $then ? [$responses] : [$responses, $then]);
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $headers = [];
            foreach ($options['headers'] ?? [] as $name => $value) {
                // MockHttpClient normalizes the headers it hands back to "Name: value" lines
                [$name, $value] = \is_int($name) ? explode(': ', $value, 2) : [$name, $value];
                $headers[$name] = \is_array($value) ? $value[0] : $value;
            }

            $this->sentHeaders[] = $headers;
            $this->sentProofs[] = $headers['DPoP'] ?? null;

            return array_shift($responses) ?? throw new \LogicException('The client made more requests than the test answers.');
        });

        return new OidcClient(
            $httpClient,
            $discovery ?? $this->discovery,
            'client-id',
            $clientAuthentication ?? new NoClientAuthentication(),
            [],
            new DpopProofFactory(new JWK(self::DPOP_JWK), 'ES256'),
        );
    }

    private static function decodePayload(string $proof): array
    {
        $payload = explode('.', $proof)[1];

        return json_decode(base64_decode(strtr($payload, '-_', '+/').str_repeat('=', 3 - (3 + \strlen($payload)) % 4)), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createDiscovery(array $configuration): OidcDiscovery
    {
        return new OidcDiscovery(
            new MockHttpClient(static fn (): MockResponse => new JsonMockResponse($configuration)),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
        );
    }
}

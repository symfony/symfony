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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientAuthenticationInterface;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretPost;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\NoClientAuthentication;
use Symfony\Component\Security\Http\OAuth2\Dpop\DpopProofFactory;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class OidcClientTest extends TestCase
{
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
        // Given
        $client = $this->createDpopClient(new JsonMockResponse(['access_token' => 'access-123']));

        // When
        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');

        // Then
        $proof = self::decodePayload($this->sentProofs[0]);
        $this->assertSame('POST', $proof['htm']);
        $this->assertSame('https://provider.example.com/token', $proof['htu']);
        $this->assertArrayNotHasKey('nonce', $proof);
    }

    public function testARefreshCarriesAProofOfTheKey()
    {
        // Given
        $client = $this->createDpopClient(new JsonMockResponse(['access_token' => 'access-456']));

        // When
        $client->refreshToken('refresh-123');

        // Then
        $this->assertSame('https://provider.example.com/token', self::decodePayload($this->sentProofs[0])['htu']);
    }

    /**
     * Section 7.1: a bound token is presented under the "DPoP" scheme, and the proof names it.
     */
    public function testUserInfoPresentsTheTokenUnderTheDpopSchemeWithItsDigest()
    {
        // Given
        $client = $this->createDpopClient(new JsonMockResponse(['sub' => 'user-1']));

        // When
        $client->fetchUserInfo('access-123');

        // Then
        $this->assertSame('DPoP access-123', $this->sentHeaders[0]['Authorization']);
        $proof = self::decodePayload($this->sentProofs[0]);
        $this->assertSame('GET', $proof['htm']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', 'access-123', true)), '+/', '-_'), '='), $proof['ath']);
    }

    public function testUserInfoStaysABearerTokenWithoutDpop()
    {
        // Given
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

        // When
        $client = new OidcClient($this->httpClient, $this->discovery, 'client-id', new NoClientAuthentication());

        // Then
        $this->assertSame(['sub' => 'user-1'], $client->fetchUserInfo('access-123'));
    }

    /**
     * Section 8: a provider may refuse a proof until it carries a nonce of its own.
     */
    public function testARequestRefusedForWantOfANonceIsSentAgainWithIt()
    {
        // Given
        $client = $this->createDpopClient(
            new JsonMockResponse(['error' => 'use_dpop_nonce'], ['http_code' => 400, 'response_headers' => ['DPoP-Nonce' => 'nonce-1']]),
            new JsonMockResponse(['access_token' => 'access-123']),
        );

        // When
        $tokens = $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');

        // Then
        $this->assertSame('access-123', $tokens['access_token']);
        $this->assertCount(2, $this->sentProofs);
        $this->assertArrayNotHasKey('nonce', self::decodePayload($this->sentProofs[0]));
        $this->assertSame('nonce-1', self::decodePayload($this->sentProofs[1])['nonce']);
    }

    /**
     * A client assertion carries a "jti" the provider remembers, so the retry is a new request.
     */
    public function testTheRetryAuthenticatesTheClientAgain()
    {
        // Given
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
            new JsonMockResponse(['access_token' => 'access-123']),
            clientAuthentication: $clientAuthentication,
        );

        // When
        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');

        // Then
        $this->assertSame(['assertion-0', 'assertion-1'], $assertions);
    }

    /**
     * A provider that keeps refusing is not retried forever: the caller reads the refusal.
     */
    public function testARequestIsNotSentAgainWhenTheProviderNamesNoNewNonce()
    {
        // Given
        $client = $this->createDpopClient(
            new JsonMockResponse(['error' => 'use_dpop_nonce'], ['http_code' => 400]),
        );

        // Then
        $this->expectException(AuthenticationException::class);

        // When
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
        // Given
        $client = $this->createDpopClient(
            new JsonMockResponse(['access_token' => 'access-123'], ['response_headers' => ['DPoP-Nonce' => 'nonce-1']]),
            new JsonMockResponse(['sub' => 'user-1']),
        );

        // When
        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'a-code-verifier');
        $client->fetchUserInfo('access-123');

        // Then
        $this->assertArrayNotHasKey('nonce', self::decodePayload($this->sentProofs[0]));
        $this->assertSame('nonce-1', self::decodePayload($this->sentProofs[1])['nonce']);
    }

    /**
     * A client whose requests are answered by the given responses, recording what it sent.
     */
    private function createDpopClient(MockResponse $response, ?MockResponse $then = null, ?ClientAuthenticationInterface $clientAuthentication = null): OidcClient
    {
        $responses = null === $then ? [$response] : [$response, $then];
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
            $this->discovery,
            'client-id',
            $clientAuthentication ?? new NoClientAuthentication(),
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

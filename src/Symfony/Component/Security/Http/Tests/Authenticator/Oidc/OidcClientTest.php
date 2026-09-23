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

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientAuthenticationInterface;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretPost;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\NoClientAuthentication;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class OidcClientTest extends TestCase
{
    private OidcDiscovery $discovery;
    private HttpClientInterface $httpClient;

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

        try {
            $client->refreshToken('refresh-123');
            $this->fail(\sprintf('Expected an "%s" to be thrown.', OidcInvalidGrantException::class));
        } catch (OidcInvalidGrantException $e) {
            $this->assertStringContainsString('The OIDC provider rejected the refresh token', $e->getMessage());

            // the rejection is kept as the cause, with the response it came in
            $this->assertInstanceOf(HttpExceptionInterface::class, $e->getPrevious());
            $this->assertSame('Token is not active', $e->getPrevious()->getResponse()->toArray(false)['error_description']);
        }
    }

    /**
     * The token endpoint answers with bearer credentials, and the request carries the
     * client secret and the authorization code or the refresh token: none of it belongs
     * in the profiler, which the traceable client is told through "extra.trace_content".
     */
    public function testTheTokenRequestsAreNotTracedByTheProfiler()
    {
        $httpClient = new TraceableHttpClient(new MockHttpClient([
            new JsonMockResponse(['access_token' => 'access-123', 'id_token' => 'id-token-abc']),
            new JsonMockResponse(['access_token' => 'access-456']),
            new JsonMockResponse(['sub' => 'user-42']),
        ]));
        $client = new OidcClient($httpClient, $this->discovery, 'test-client-id', new ClientSecretPost('test-client-secret'));

        $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'code-verifier');
        $client->refreshToken('refresh-123');
        $client->fetchUserInfo('access-456');

        $traces = $httpClient->getTracedRequests();
        $this->assertCount(3, $traces);

        foreach ([0, 1] as $tokenRequest) {
            $this->assertNull($traces[$tokenRequest]['content']);
            $this->assertArrayNotHasKey('body', $traces[$tokenRequest]['options']);
            $this->assertStringNotContainsString('test-client-secret', json_encode($traces[$tokenRequest]['options']));
        }

        // the UserInfo response holds the claims, which are worth seeing
        $this->assertSame(['sub' => 'user-42'], $traces[2]['content']);
    }

    /**
     * A stack trace records the arguments of its frames, and an unhandled exception
     * displays them: the credentials passed to the client must not be among them.
     */
    public function testTheCredentialsAreHiddenFromStackTraces()
    {
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');

        try {
            $this->httpClient->method('request')->willThrowException(new \RuntimeException('Unexpected failure.'));
            $client = $this->createClient();

            foreach ([
                static fn () => $client->exchangeCode('raw-code', 'https://app.example.com/callback', 'raw-verifier'),
                static fn () => $client->refreshToken('raw-refresh-token'),
                static fn () => $client->fetchUserInfo('raw-access-token'),
            ] as $call) {
                try {
                    $call();
                    $this->fail('Expected the client to fail.');
                } catch (\RuntimeException $e) {
                    $this->assertStringNotContainsString('raw-', $e->getTraceAsString());

                    // the frames of the component only: the mocked HTTP client receives
                    // the request options as a plain argument, which is on the contracts
                    foreach ($e->getTrace() as $frame) {
                        if (!str_starts_with($frame['class'] ?? '', 'Symfony\\Component\\Security\\')) {
                            continue;
                        }

                        foreach ($frame['args'] ?? [] as $arg) {
                            $this->assertStringNotContainsString('raw-', json_encode($arg));
                        }
                    }
                }
            }
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArgs);
        }
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

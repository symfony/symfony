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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcAuthorizationCodeFlowState;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcConfiguration;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OidcClientTest extends TestCase
{
    private OidcDiscovery $discovery;
    private OidcAuthorizationCodeFlowState $stateStorage;
    private HttpClientInterface $httpClient;
    private OidcConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OidcConfiguration(
            authorizationEndpoint: 'https://provider.example.com/authorize',
            tokenEndpoint: 'https://provider.example.com/token',
            issuer: 'https://provider.example.com',
            jwksUri: 'https://provider.example.com/jwks',
            userinfoEndpoint: 'https://provider.example.com/userinfo',
            endSessionEndpoint: 'https://provider.example.com/logout',
        );

        $this->discovery = $this->createMock(OidcDiscovery::class);
        $this->discovery->method('getConfiguration')->willReturn($this->configuration);

        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $requestStack->push($request);

        $this->stateStorage = new OidcAuthorizationCodeFlowState($requestStack, 'main');
    }

    public function testStartAuthorizationReturnsRedirectResponse()
    {
        $client = $this->createClient();

        $response = $client->startAuthorization();

        $this->assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://provider.example.com/authorize?', $location);

        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $this->assertSame('code', $params['response_type']);
        $this->assertSame('test-client-id', $params['client_id']);
        $this->assertSame('https://app.example.com/oidc/callback', $params['redirect_uri']);
        $this->assertSame('openid', $params['scope']);
        $this->assertNotEmpty($params['state']);
        $this->assertNotEmpty($params['nonce']);
    }

    public function testStartAuthorizationWithPkce()
    {
        $client = $this->createClient(pkceEnabled: true);

        $response = $client->startAuthorization();
        $location = $response->headers->get('Location');
        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $this->assertSame('S256', $params['code_challenge_method']);
        $this->assertNotEmpty($params['code_challenge']);
        $this->assertNotNull($this->stateStorage->getCodeVerifier());
    }

    public function testStartAuthorizationWithoutPkce()
    {
        $client = $this->createClient(pkceEnabled: false);

        $response = $client->startAuthorization();
        $location = $response->headers->get('Location');
        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $this->assertArrayNotHasKey('code_challenge', $params);
        $this->assertArrayNotHasKey('code_challenge_method', $params);
    }

    public function testStartAuthorizationStoresStateInSession()
    {
        $client = $this->createClient();

        $response = $client->startAuthorization();
        $location = $response->headers->get('Location');
        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $this->assertSame($params['state'], $this->stateStorage->getState());
        $this->assertSame($params['nonce'], $this->stateStorage->getNonce());
    }

    public function testHandleCallbackExchangesCodeForTokens()
    {
        $state = bin2hex(random_bytes(16));
        $this->stateStorage->setState($state);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'access-123',
            'id_token' => 'id-456',
            'refresh_token' => 'refresh-789',
            'expires_in' => 3600,
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://provider.example.com/token', $this->callback(function (array $options) use ($state) {
                $this->assertSame('authorization_code', $options['body']['grant_type']);
                $this->assertSame('auth-code-abc', $options['body']['code']);
                $this->assertSame('test-client-secret', $options['body']['client_secret']);

                return true;
            }))
            ->willReturn($response);

        $client = $this->createClient(pkceEnabled: false);
        $request = Request::create('/oidc/callback?code=auth-code-abc&state='.$state);

        $tokens = $client->handleCallback($request);

        $this->assertSame('access-123', $tokens->accessToken);
        $this->assertSame('id-456', $tokens->idToken);
        $this->assertSame('refresh-789', $tokens->refreshToken);
    }

    public function testHandleCallbackWithInvalidState()
    {
        $this->stateStorage->setState('expected-state');

        $client = $this->createClient();
        $request = Request::create('/oidc/callback?code=auth-code&state=wrong-state');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OIDC state');

        $client->handleCallback($request);
    }

    public function testHandleCallbackWithProviderError()
    {
        $client = $this->createClient();
        $request = Request::create('/oidc/callback?error=access_denied&error_description=User+denied+access');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User denied access');

        $client->handleCallback($request);
    }

    public function testHandleCallbackClearsSessionState()
    {
        $state = bin2hex(random_bytes(16));
        $this->stateStorage->setState($state);
        $this->stateStorage->setNonce('nonce-value');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'access-123',
            'id_token' => 'id-456',
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $client = $this->createClient(pkceEnabled: false);
        $request = Request::create('/oidc/callback?code=auth-code&state='.$state);
        $client->handleCallback($request);

        $this->assertNull($this->stateStorage->getState());
        $this->assertNull($this->stateStorage->getNonce());
    }

    public function testFetchUserInfo()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'sub' => '123',
            'email' => 'test@example.com',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://provider.example.com/userinfo', [
                'auth_bearer' => 'access-token',
            ])
            ->willReturn($response);

        $client = $this->createClient();
        $claims = $client->fetchUserInfo('access-token');

        $this->assertSame('123', $claims['sub']);
        $this->assertSame('test@example.com', $claims['email']);
    }

    public function testBuildEndSessionUrl()
    {
        $client = $this->createClient();
        $url = $client->buildEndSessionUrl('my-id-token', 'https://app.example.com/');

        $this->assertStringStartsWith('https://provider.example.com/logout?', $url);
        $this->assertStringContainsString('id_token_hint=my-id-token', $url);
        $this->assertStringContainsString('post_logout_redirect_uri=', $url);
    }

    private function createClient(bool $pkceEnabled = true): OidcClient
    {
        return new OidcClient(
            httpClient: $this->httpClient,
            discovery: $this->discovery,
            stateStorage: $this->stateStorage,
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            callbackUrl: 'https://app.example.com/oidc/callback',
            scopes: ['openid'],
            pkceEnabled: $pkceEnabled,
        );
    }
}

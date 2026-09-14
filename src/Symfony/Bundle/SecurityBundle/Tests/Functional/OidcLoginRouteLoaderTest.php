<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OidcLoginRouteLoaderTest extends AbstractWebTestCase
{
    public function testRouteLoaderCanBeImportedWithoutOidcLoginFirewall()
    {
        $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config.yml']);

        $routes = static::getContainer()->get('router')->getRouteCollection();

        $this->assertSame([], array_filter(array_keys($routes->all()), static fn (string $name) => str_starts_with($name, '_oidc_login_')));
        $this->assertNotNull($routes->get('_logout_main'));
    }

    public function testRouteLoaderDeclaresTheCallbackAndStartRoutes()
    {
        $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc.yml']);

        $routes = static::getContainer()->get('router')->getRouteCollection();

        $this->assertSame('/oidc/callback', $routes->get('_oidc_login_callback_oidc')?->getPath());

        $startRoute = $routes->get('_oidc_login_start_oidc');
        $this->assertSame('/oidc/start', $startRoute?->getPath());
        $this->assertSame('security.authenticator.oidc_login.start_controller', $startRoute->getDefault('_controller'));
        $this->assertSame('oidc', $startRoute->getDefault('firewallName'));
    }

    public function testStartRouteRedirectsToTheProvider()
    {
        $discoveryResponse = new MockResponse(json_encode([
            'issuer' => 'https://accounts.example.com',
            'authorization_endpoint' => 'https://accounts.example.com/authorize',
            'token_endpoint' => 'https://accounts.example.com/token',
            'userinfo_endpoint' => 'https://accounts.example.com/userinfo',
            'jwks_uri' => 'https://accounts.example.com/jwks',
        ]), ['response_headers' => ['content-type' => 'application/json']]);

        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc.yml']);
        $client->getContainer()->set('Symfony\Contracts\HttpClient\HttpClientInterface', new MockHttpClient($discoveryResponse));

        $client->request('GET', '/oidc/start');
        $response = $client->getResponse();

        $this->assertSame(302, $response->getStatusCode());

        $location = parse_url($response->headers->get('Location'));
        parse_str($location['query'], $query);

        $this->assertSame('https', $location['scheme']);
        $this->assertSame('accounts.example.com', $location['host']);
        $this->assertSame('/authorize', $location['path']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('client_id', $query['client_id']);
        $this->assertSame('http://localhost/oidc/callback', $query['redirect_uri']);
        $this->assertSame('openid', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['state']);
        $this->assertNotEmpty($query['nonce']);
        $this->assertNotEmpty($query['code_challenge']);
    }

    public function testTheAuthorizationRequestEventCanTailorTheParams()
    {
        $discoveryResponse = new MockResponse(json_encode([
            'issuer' => 'https://accounts.example.com',
            'authorization_endpoint' => 'https://accounts.example.com/authorize',
            'token_endpoint' => 'https://accounts.example.com/token',
            'userinfo_endpoint' => 'https://accounts.example.com/userinfo',
            'jwks_uri' => 'https://accounts.example.com/jwks',
        ]), ['response_headers' => ['content-type' => 'application/json']]);

        // the config declares two listeners: one on the global dispatcher, which reaches the
        // event through RegisterGlobalSecurityEventListenersPass, and one on the firewall
        // dispatcher the authenticator dispatches on; each sets a parameter of its own
        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc_event.yml']);
        $client->getContainer()->set('Symfony\Contracts\HttpClient\HttpClientInterface', new MockHttpClient($discoveryResponse));

        $client->request('GET', '/oidc/start');
        $response = $client->getResponse();

        $this->assertSame(302, $response->getStatusCode());

        parse_str(parse_url($response->headers->get('Location'), \PHP_URL_QUERY), $query);

        $this->assertSame('fr-FR', $query['ui_locales']);
        $this->assertSame('login', $query['prompt']);
        $this->assertSame('code', $query['response_type']);
        $this->assertNotEmpty($query['state']);
        $this->assertNotEmpty($query['code_challenge']);
    }

    public function testTheAccessTokenIsRenewedOnTheNextRequest()
    {
        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc_refresh.yml']);
        $client->loginUser(new InMemoryUser('john', 'test', ['ROLE_USER']), 'oidc', [
            'oidc_access_token' => 'access-123',
            'oidc_refresh_token' => 'refresh-123',
            'oidc_access_token_expires_at' => time() - 1,
        ]);
        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient($this->mockProvider([
            'access_token' => 'access-456',
            'refresh_token' => 'refresh-456',
            'expires_in' => 300,
        ])));
        $client->getContainer()->get('security.token_storage')->setToken(null);

        $client->request('GET', '/oidc/start');

        $token = unserialize($client->getRequest()->getSession()->get('_security_oidc'));
        $this->assertSame('access-456', $token->getAttribute('oidc_access_token'));
        $this->assertSame('refresh-456', $token->getAttribute('oidc_refresh_token'));
        $this->assertGreaterThan(time(), $token->getAttribute('oidc_access_token_expires_at'));
    }

    /**
     * A lazy firewall, which is what the security recipe configures, asks every listener
     * whether it supports the request before it restores the security token, so a listener
     * that answers on the strength of that token is dropped from the chain for good.
     */
    public function testTheAccessTokenIsRenewedOnALazyFirewall()
    {
        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc_refresh_lazy.yml']);
        $client->loginUser(new InMemoryUser('john', 'test', ['ROLE_USER']), 'oidc', [
            'oidc_access_token' => 'access-123',
            'oidc_refresh_token' => 'refresh-123',
            'oidc_access_token_expires_at' => time() - 1,
        ]);
        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient($this->mockProvider([
            'access_token' => 'access-456',
            'refresh_token' => 'refresh-456',
            'expires_in' => 300,
        ])));
        $client->getContainer()->get('security.token_storage')->setToken(null);

        $client->request('GET', '/oidc/start');

        $token = unserialize($client->getRequest()->getSession()->get('_security_oidc'));
        $this->assertSame('access-456', $token->getAttribute('oidc_access_token'));
        $this->assertSame('refresh-456', $token->getAttribute('oidc_refresh_token'));
    }

    public function testTheUserIsLoggedOutWhenTheProviderRejectsTheRefreshToken()
    {
        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc_refresh.yml']);
        $client->loginUser(new InMemoryUser('john', 'test', ['ROLE_USER']), 'oidc', [
            'oidc_access_token' => 'access-123',
            'oidc_refresh_token' => 'refresh-123',
            'oidc_access_token_expires_at' => time() - 1,
        ]);
        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient($this->mockProvider(['error' => 'invalid_grant'], 400)));
        $client->getContainer()->get('security.token_storage')->setToken(null);

        $client->request('GET', '/oidc/start');

        $this->assertNull($client->getRequest()->getSession()->get('_security_oidc'));
    }

    public function testTheUserIsLoggedOutOnALazyFirewallWhenTheProviderRejectsTheRefreshToken()
    {
        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc_refresh_lazy.yml']);
        $client->loginUser(new InMemoryUser('john', 'test', ['ROLE_USER']), 'oidc', [
            'oidc_access_token' => 'access-123',
            'oidc_refresh_token' => 'refresh-123',
            'oidc_access_token_expires_at' => time() - 1,
        ]);
        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient($this->mockProvider(['error' => 'invalid_grant'], 400)));
        $client->getContainer()->get('security.token_storage')->setToken(null);

        $client->request('GET', '/oidc/start');

        $this->assertNull($client->getRequest()->getSession()->get('_security_oidc'));
    }

    public function testTheAccessTokenIsNotRenewedWithoutTheOption()
    {
        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc.yml']);
        $client->loginUser(new InMemoryUser('john', 'test', ['ROLE_USER']), 'oidc', [
            'oidc_access_token' => 'access-123',
            'oidc_refresh_token' => 'refresh-123',
            'oidc_access_token_expires_at' => time() - 1,
        ]);
        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient($this->mockProvider(['access_token' => 'access-456'])));
        $client->getContainer()->get('security.token_storage')->setToken(null);

        $client->request('GET', '/oidc/start');

        $token = unserialize($client->getRequest()->getSession()->get('_security_oidc'));
        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
    }

    /**
     * @param array<string, mixed> $tokenEndpointResponse
     */
    private function mockProvider(array $tokenEndpointResponse, int $tokenEndpointStatusCode = 200): \Closure
    {
        return static function (string $method, string $url) use ($tokenEndpointResponse, $tokenEndpointStatusCode): MockResponse {
            if (str_contains($url, '/.well-known/openid-configuration')) {
                return new JsonMockResponse([
                    'issuer' => 'https://accounts.example.com',
                    'authorization_endpoint' => 'https://accounts.example.com/authorize',
                    'token_endpoint' => 'https://accounts.example.com/token',
                    'userinfo_endpoint' => 'https://accounts.example.com/userinfo',
                    'jwks_uri' => 'https://accounts.example.com/jwks',
                ]);
            }

            if (str_contains($url, '/token')) {
                return new JsonMockResponse($tokenEndpointResponse, ['http_code' => $tokenEndpointStatusCode]);
            }

            throw new \LogicException(\sprintf('Unexpected request to "%s".', $url));
        };
    }
}

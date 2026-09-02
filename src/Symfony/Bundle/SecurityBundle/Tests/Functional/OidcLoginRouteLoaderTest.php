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
use Symfony\Component\HttpClient\Response\MockResponse;

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
}

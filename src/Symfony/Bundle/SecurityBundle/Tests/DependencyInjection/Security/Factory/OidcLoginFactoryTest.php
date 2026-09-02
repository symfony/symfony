<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Security\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\OidcLoginFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class OidcLoginFactoryTest extends TestCase
{
    public function testBasicServiceConfiguration()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'check_path' => '/oidc/callback',
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'main', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login'));
        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login.main'));
        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login.discovery.main'));
        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login.client.main'));

        // the discovery service and the client id are injected directly, not fetched through the OIDC client
        $authenticator = $container->getDefinition('security.authenticator.oidc_login.main');
        $this->assertEquals(new Reference('security.authenticator.oidc_login.discovery.main'), $authenticator->getArgument(3));
        $this->assertSame('my-client-id', $authenticator->getArgument(5));

        // the endpoints checked against the transport of the discovery document stay wired: the
        // per-firewall child definition only replaces the issuer and the cache TTL, so it
        // inherits this argument from the abstract definition
        $discovery = $container->getDefinition('security.authenticator.oidc_login.discovery');
        $this->assertSame(['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'], $discovery->getArgument(6));

        // the per-firewall discovery definition carries the kernel.reset tag with method reset
        $discoveryMain = $container->getDefinition('security.authenticator.oidc_login.discovery.main');
        $this->assertSame(['kernel.reset' => [['method' => 'reset']]], $discoveryMain->getTags());
    }

    public function testFirewallUserProviderIsInjected()
    {
        // the user provider loads the user, so that the token stored in the session can be
        // refreshed by the very same provider on the next request
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'security.user.provider.concrete.oidc');

        $authenticator = $container->getDefinition('security.authenticator.oidc_login.main');
        $this->assertEquals(new Reference('security.user.provider.concrete.oidc'), $authenticator->getArgument(1));
    }

    public function testScopeDefaultsToOpenid()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);

        $this->assertSame(['openid'], $finalizedConfig['scope']);
    }

    public function testScopeIsInjectedIntoTheAuthenticatorOptions()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            // a single string is accepted, so that an environment variable can carry every scope
            'scope' => 'openid profile email',
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $options = $container->getDefinition('security.authenticator.oidc_login.main')->getArgument(8);
        $this->assertSame(['openid profile email'], $options['scope']);
    }

    public function testGetKey()
    {
        $factory = new OidcLoginFactory();

        $this->assertSame('oidc-login', $factory->getKey());
    }

    public function testGetPriority()
    {
        $factory = new OidcLoginFactory();

        $this->assertSame(-25, $factory->getPriority());
    }

    public function testRequiredOptions()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);
    }

    public function testCheckPathDefaultsToCallback()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);

        $this->assertSame('/oidc/callback', $finalizedConfig['check_path']);
    }

    public function testDiscoveryCacheTtlDefaultsToAnHour()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);

        $this->assertSame(3600, $finalizedConfig['discovery_cache_ttl']);
    }

    public function testAllowedTimeDriftDefaultsToZero()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);

        $this->assertSame(0, $finalizedConfig['allowed_time_drift']);
    }

    public function testProviderUriIsInjectedAsTheDiscoveryIssuer()
    {
        // the value is passed as it is configured: an environment variable is still a
        // placeholder here, so OidcDiscovery is what trims it and builds the discovery URL
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com/',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $discovery = $container->getDefinition('security.authenticator.oidc_login.discovery.main');
        $this->assertSame('https://provider.example.com/', $discovery->getArgument(3));
        // the configuration URL is left to the parent definition, relative to the issuer
        $this->assertArrayNotHasKey('index_2', $discovery->getArguments());
    }

    public function testDiscoveryCacheTtlIsInjectedIntoTheDiscoveryService()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'discovery_cache_ttl' => 60,
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $this->assertSame(60, $container->getDefinition('security.authenticator.oidc_login.discovery.main')->getArgument(4));
    }

    public function testAllowedTimeDriftIsInjectedIntoTheIdTokenService()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'allowed_time_drift' => 60,
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $idToken = $container->getDefinition('security.authenticator.oidc_login.id_token.main');
        $this->assertSame(60, $idToken->getArgument(1));

        $authenticator = $container->getDefinition('security.authenticator.oidc_login.main');
        $this->assertEquals(new Reference('security.authenticator.oidc_login.id_token.main'), $authenticator->getArgument(4));
    }

    #[DataProvider('provideNegativeTtls')]
    public function testRejectsNegativeTtls(string $option)
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            $option => -1,
        ], $factory);
    }

    public static function provideNegativeTtls(): iterable
    {
        yield 'discovery cache TTL' => ['discovery_cache_ttl'];
        yield 'allowed time drift' => ['allowed_time_drift'];
    }

    public function testCallbackRouteIsRegistered()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'check_path' => '/oidc/callback',
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'main', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login.route_loader'));
        $this->assertSame(['main' => '/oidc/callback'], $container->getParameter('security.oidc_login.callback_uris'));
    }

    public function testCallbackRouteNameIsNotRegistered()
    {
        // check_path may be a route name, in which case no route is declared for it
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'check_path' => 'oidc_callback_route',
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'main', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login.route_loader'));
        $this->assertSame('oidc_callback_route', $finalizedConfig['check_path']);
        // no route is declared for a route name, but the parameter the route loader
        // is wired on must exist
        $this->assertSame([], $container->getParameter('security.oidc_login.callback_uris'));
    }

    public function testRejectsNonHttpsProviderUri()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $this->processConfig([
            'provider_uri' => 'http://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);
    }

    public function testAcceptsAnUppercaseSchemeInTheProviderUri()
    {
        // a URI scheme is case-insensitive, and parse_url() returns it as it is written
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'HTTPS://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);

        $this->assertSame('HTTPS://provider.example.com', $finalizedConfig['provider_uri']);
    }

    /**
     * @param string $providerUri a loopback host or a name reserved for testing (RFC 2606, RFC 6761)
     */
    #[DataProvider('provideLocalDevelopmentProviderUris')]
    public function testAllowsHttpProviderUriForLocalDevelopment(string $providerUri)
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => $providerUri,
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);

        $this->assertSame($providerUri, $finalizedConfig['provider_uri']);
    }

    public static function provideLocalDevelopmentProviderUris(): iterable
    {
        yield 'localhost' => ['http://localhost:8080'];
        yield 'IPv4 loopback' => ['http://127.0.0.1:8080'];
        yield 'IPv6 loopback' => ['http://[::1]:8080'];
        yield 'localhost subdomain' => ['http://keycloak.localhost'];
        yield 'test TLD' => ['http://keycloak.test'];
    }

    private function processConfig(array $config, OidcLoginFactory $factory): array
    {
        $nodeDefinition = new ArrayNodeDefinition('oidc-login');
        $factory->addConfiguration($nodeDefinition);

        $node = $nodeDefinition->getNode();
        $normalizedConfig = $node->normalize($config);

        return $node->finalize($normalizedConfig);
    }
}

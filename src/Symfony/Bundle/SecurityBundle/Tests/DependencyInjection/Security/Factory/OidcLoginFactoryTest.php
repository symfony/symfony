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
use Symfony\Component\DependencyInjection\ChildDefinition;
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

        // the endpoints checked against the transport of the discovery document stay wired
        // on the per-firewall child definition, which computes them from the claims source
        $discoveryMain = $container->getDefinition('security.authenticator.oidc_login.discovery.main');
        $this->assertSame(['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'], $discoveryMain->getArgument(6));

        // the per-firewall discovery definition carries the kernel.reset tag with method reset
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

    public function testEndSessionListenerRegistration()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'check_path' => '/oidc/callback',
            'enable_end_session' => true,
            'post_logout_redirect_path' => '/logged-out',
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'main', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login.end_session_listener.main'));
    }

    public function testEndSessionListenerNotRegisteredByDefault()
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

        $this->assertFalse($container->hasDefinition('security.authenticator.oidc_login.end_session_listener.main'));
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

    public function testDefaultConfiguration()
    {
        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'check_path' => '/oidc/callback',
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);

        $this->assertSame('/oidc/callback', $finalizedConfig['check_path']);
        $this->assertSame(['openid'], $finalizedConfig['scope']);
        $this->assertTrue($finalizedConfig['pkce']['enabled']);
        $this->assertSame('S256', $finalizedConfig['pkce']['method']);
        $this->assertSame(3600, $finalizedConfig['discovery_cache_ttl']);
        $this->assertSame([], $finalizedConfig['authorization_params']);
        $this->assertArrayNotHasKey('max_age', $finalizedConfig);
    }

    public function testMaxAgeAndAuthorizationParamsArePassedToTheAuthenticator()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'check_path' => '/oidc/callback',
            'max_age' => 3600,
            'authorization_params' => ['prompt' => 'consent', 'ui_locales' => 'fr'],
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'main', $finalizedConfig, 'userprovider');

        $authenticator = $container->getDefinition('security.authenticator.oidc_login.main');

        $this->assertSame(3600, $authenticator->getArgument(8)['max_age']);
        $this->assertSame(['prompt' => 'consent', 'ui_locales' => 'fr'], $authenticator->getArgument(9));
    }

    public function testAuthorizationParamsCannotSetTheManagedParameters()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "authorization_params" option cannot set');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'authorization_params' => ['code_challenge' => ''],
        ], $factory);
    }

    public function testPkceMethodRejectsUnknownValue()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'pkce' => ['method' => 'S512'],
        ], $factory);
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

        $this->assertSame('oidc_callback_route', $finalizedConfig['check_path']);
        // no route is declared for a route name, but the parameter the route loader
        // is wired on must exist
        $this->assertSame([], $container->getParameter('security.oidc_login.callback_uris'));
    }

    public function testStartRouteIsRegistered()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'main', $finalizedConfig, 'userprovider');

        $this->assertSame(['main' => '/oidc/start'], $container->getParameter('security.oidc_login.start_paths'));

        $locator = $container->getDefinition('security.authenticator.oidc_login.start_controller')->getArgument(0);
        $this->assertEquals(['main' => new Reference('security.authenticator.oidc_login.main')], $locator->getValues());
    }

    public function testStartRouteNameIsNotRegistered()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'start_path' => 'oidc_start_route',
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'main', $finalizedConfig, 'userprovider');

        $this->assertSame([], $container->getParameter('security.oidc_login.start_paths'));
        // the controller still serves the firewall, as the named route may point to it
        $locator = $container->getDefinition('security.authenticator.oidc_login.start_controller')->getArgument(0);
        $this->assertEquals(['main' => new Reference('security.authenticator.oidc_login.main')], $locator->getValues());
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

    public function testPkceCanBeDisabled()
    {
        // e.g. for a provider that does not support PKCE yet
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'pkce' => ['enabled' => false, 'method' => 'plain'],
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertFalse($config['pkce']['enabled']);

        $options = $container->getDefinition('security.authenticator.oidc_login.main')->getArgument(8);
        $this->assertFalse($options['pkce_enabled']);
        $this->assertSame('plain', $options['pkce_method']);
    }

    public function testIdTokenSignatureIsVerifiedByDefault()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertTrue($config['id_token_signature']['required']);
        // "RS256" is the only algorithm OIDC Core 1.0 requires providers to support
        $this->assertSame(['RS256'], $config['id_token_signature']['algorithms']);
        $this->assertTrue($config['id_token_signature']['enforce_key_usage_verification']);

        $verifier = $container->getDefinition('security.authenticator.oidc_login.signature_verifier.main');
        $this->assertSame('security.authenticator.oidc_login.signature_verifier', $verifier->getParent());
        // the firewall discovery document is where the JWKS URI is announced
        $this->assertEquals(new Reference('security.authenticator.oidc_login.discovery.main'), $verifier->getArgument(0));
        $this->assertSame(['RS256'], $verifier->getArgument(3));
        $this->assertSame(3600, $verifier->getArgument(4));
        $this->assertTrue($verifier->getArgument(5));

        $authenticator = $container->getDefinition('security.authenticator.oidc_login.main');
        $this->assertEquals(new Reference('security.authenticator.oidc_login.signature_verifier.main'), $authenticator->getArgument(10));
    }

    public function testIdTokenSignatureVerificationCanBeTurnedOff()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'id_token_signature' => ['required' => false],
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertFalse($container->hasDefinition('security.authenticator.oidc_login.signature_verifier.main'));

        $authenticator = $container->getDefinition('security.authenticator.oidc_login.main');
        $this->assertNull($authenticator->getArgument(10));
    }

    public function testIdTokenSignatureAlgorithmsAndKeyUsageAreConfigurable()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'discovery_cache_ttl' => 60,
            'id_token_signature' => [
                'algorithms' => ['ES256', 'PS256'],
                'enforce_key_usage_verification' => false,
            ],
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $verifier = $container->getDefinition('security.authenticator.oidc_login.signature_verifier.main');
        $this->assertSame(['ES256', 'PS256'], $verifier->getArgument(3));
        // the JWKS falls back to the discovery TTL when the provider advertises none
        $this->assertSame(60, $verifier->getArgument(4));
        $this->assertFalse($verifier->getArgument(5));
    }

    public function testASingleIdTokenSignatureAlgorithmCanBeGivenAsAString()
    {
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'id_token_signature' => ['algorithms' => 'ES256'],
        ], $factory);

        $this->assertSame(['ES256'], $config['id_token_signature']['algorithms']);
    }

    public function testConfidentialClientIsWiredByDefault()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $client = $container->getDefinition('security.authenticator.oidc_login.client.main');
        $this->assertInstanceOf(ChildDefinition::class, $client);
        $this->assertSame('security.authenticator.oidc_login.client', $client->getParent());
        $this->assertSame('my-client-secret', $client->getArgument(3));
        $this->assertSame('client_secret_post', $client->getArgument(4));
    }

    public function testPublicClientIsWiredWhenTheTokenEndpointNeedsNoAuthentication()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'token_endpoint_auth_method' => 'none',
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $client = $container->getDefinition('security.authenticator.oidc_login.client.main');
        $this->assertInstanceOf(ChildDefinition::class, $client);
        $this->assertSame('security.authenticator.oidc_login.public_client', $client->getParent());
        $this->assertEquals(new Reference('security.authenticator.oidc_login.discovery.main'), $client->getArgument(1));
        $this->assertSame('my-client-id', $client->getArgument(2));
        // a public client takes neither a secret nor an authentication method
        $this->assertArrayNotHasKey('index_3', $client->getArguments());
        $this->assertArrayNotHasKey('index_4', $client->getArguments());
    }

    #[DataProvider('provideSecretBasedAuthMethods')]
    public function testRejectsAMissingClientSecretForSecretBasedAuthentication(?string $tokenEndpointAuthMethod)
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "client_secret" is required by the "token_endpoint_auth_method" in use');

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
        ];
        if (null !== $tokenEndpointAuthMethod) {
            $config['token_endpoint_auth_method'] = $tokenEndpointAuthMethod;
        }

        $this->processConfig($config, $factory);
    }

    public static function provideSecretBasedAuthMethods(): iterable
    {
        yield 'implicit default' => [null];
        yield 'client_secret_post' => ['client_secret_post'];
        yield 'client_secret_basic' => ['client_secret_basic'];
    }

    public function testRejectsAClientSecretForAPublicClient()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "client_secret" must not be set when "token_endpoint_auth_method" is "none"');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'token_endpoint_auth_method' => 'none',
        ], $factory);
    }

    public function testAllowsAnExplicitNullClientSecretForAPublicClient()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => null,
            'token_endpoint_auth_method' => 'none',
        ], $factory);

        $this->assertNull($finalizedConfig['client_secret']);
    }

    public function testRejectsAnEmptyClientSecret()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "client_secret" is required by the "token_endpoint_auth_method" in use');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => '',
        ], $factory);
    }

    public function testPublicClientDefaults()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'token_endpoint_auth_method' => 'none',
        ], $factory);

        $this->assertNull($finalizedConfig['client_secret']);
    }

    public function testTokenEndpointAuthMethodDefaultsToClientSecretPost()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);

        $this->assertSame('client_secret_post', $finalizedConfig['token_endpoint_auth_method']);
    }

    public function testRejectsAPublicClientWithoutPkce()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "pkce.enabled" option cannot be false when "token_endpoint_auth_method" is "none"');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'token_endpoint_auth_method' => 'none',
            'pkce' => ['enabled' => false],
        ], $factory);
    }

    public function testAPublicClientKeepsPkceEnabledByDefault()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'token_endpoint_auth_method' => 'none',
        ], $factory);

        $this->assertTrue($finalizedConfig['pkce']['enabled']);
    }

    public function testRejectsTurningTheIdTokenSignatureVerificationOffForAPublicClient()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "id_token_signature.required" option cannot be false when "token_endpoint_auth_method" is "none"');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'token_endpoint_auth_method' => 'none',
            'id_token_signature' => ['required' => false],
        ], $factory);
    }

    public function testAConfidentialClientMayTurnTheIdTokenSignatureVerificationOff()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'id_token_signature' => ['required' => false],
        ], $factory);

        $this->assertFalse($finalizedConfig['id_token_signature']['required']);
    }

    public function testAPublicClientVerifiesTheIdTokenSignatureByDefault()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'token_endpoint_auth_method' => 'none',
        ], $factory);

        $this->assertTrue($finalizedConfig['id_token_signature']['required']);
    }

    public function testClaimsSourceAndUserIdentifierDefaults()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);

        $this->assertSame('userinfo', $finalizedConfig['user_data_source']);
        $this->assertSame('sub', $finalizedConfig['user_identifier_claim']);
    }

    public function testClaimsMayBeSourcedFromTheIdToken()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'user_data_source' => 'id_token',
            'user_identifier_claim' => 'email',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $options = $container->getDefinition('security.authenticator.oidc_login.main')->getArgument(8);
        $this->assertSame('id_token', $options['user_data_source']);
        $this->assertSame('email', $options['user_identifier_claim']);
    }

    public function testUserinfoEndpointRequiredWithTheUserinfoClaimsSource()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertSame(['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'], $container->getDefinition('security.authenticator.oidc_login.discovery.main')->getArgument(6));
    }

    public function testUserinfoEndpointNotRequiredWithTheIdTokenClaimsSource()
    {
        // a provider putting the claims in the ID token does not necessarily announce
        // any UserInfo endpoint, so the discovery must not require one then
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'user_data_source' => 'id_token',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertSame(['authorization_endpoint', 'token_endpoint'], $container->getDefinition('security.authenticator.oidc_login.discovery.main')->getArgument(6));
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

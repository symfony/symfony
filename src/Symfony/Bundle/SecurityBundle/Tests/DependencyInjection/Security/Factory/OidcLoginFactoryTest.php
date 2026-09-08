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
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\NoClientAuthentication;

class OidcLoginFactoryTest extends TestCase
{
    /**
     * A JSON-encoded private JWK, the shape the "private_key_jwt" method takes its key in.
     */
    private const SIGNING_KEY = '{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}';

    public function testBasicServiceConfiguration()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
        ], $factory);

        $this->assertSame(['openid'], $finalizedConfig['scope']);
    }

    public function testScopeIsInjectedIntoTheAuthenticatorOptions()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
        $this->assertFalse($finalizedConfig['refresh_access_token']['enabled']);
        $this->assertSame(30, $finalizedConfig['refresh_access_token']['leeway']);
        $this->assertArrayNotHasKey('max_age', $finalizedConfig);
    }

    public function testMaxAgeAndAuthorizationParamsArePassedToTheAuthenticator()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
            'pkce' => ['method' => 'S512'],
        ], $factory);
    }

    public function testRequiredOptions()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);
    }

    public function testCheckPathDefaultsToCallback()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);

        $this->assertSame('/oidc/callback', $finalizedConfig['check_path']);
    }

    public function testDiscoveryCacheTtlDefaultsToAnHour()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);

        $this->assertSame(3600, $finalizedConfig['discovery_cache_ttl']);
    }

    public function testAllowedTimeDriftDefaultsToZero()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
        ], $factory);
    }

    public function testAcceptsAnUppercaseSchemeInTheProviderUri()
    {
        // a URI scheme is case-insensitive, and parse_url() returns it as it is written
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'HTTPS://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);

        $this->assertSame('HTTPS://provider.example.com', $finalizedConfig['provider_uri']);
    }

    /**
     * @param string $providerUri a loopback host or a name RFC 6761, Section 6.3 reserves for the loopback interface
     */
    #[DataProvider('provideLocalDevelopmentProviderUris')]
    public function testAllowsHttpProviderUriForLocalDevelopment(string $providerUri)
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => $providerUri,
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);

        $this->assertSame($providerUri, $finalizedConfig['provider_uri']);
    }

    public static function provideLocalDevelopmentProviderUris(): iterable
    {
        yield 'localhost' => ['http://localhost:8080'];
        yield 'IPv4 loopback' => ['http://127.0.0.1:8080'];
        yield 'IPv6 loopback' => ['http://[::1]:8080'];
        yield 'localhost subdomain' => ['http://keycloak.localhost'];
    }

    public function testRejectsATestDomainProviderUri()
    {
        // RFC 6761, Section 6.2 reserves ".test" without tying it to the loopback interface,
        // so the name resolves like any other and plain HTTP is not confidential there
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "provider_uri" must use HTTPS');

        $this->processConfig([
            'provider_uri' => 'http://keycloak.test',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);
    }

    public function testPkceCanBeDisabled()
    {
        // e.g. for a provider that does not support PKCE yet
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
            'id_token_signature' => ['algorithms' => 'ES256'],
        ], $factory);

        $this->assertSame(['ES256'], $config['id_token_signature']['algorithms']);
    }

    public function testTheClientAuthenticationServiceIsInjectedIntoTheClient()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $client = $container->getDefinition('security.authenticator.oidc_login.client.main');
        $this->assertInstanceOf(ChildDefinition::class, $client);
        $this->assertSame('security.authenticator.oidc_login.client', $client->getParent());
        $this->assertEquals(new Reference('security.authenticator.oidc_login.discovery.main'), $client->getArgument(1));
        $this->assertSame('my-client-id', $client->getArgument(2));
        $this->assertEquals(new Reference('app.client_authentication'), $client->getArgument(3));
    }

    /**
     * A public client points at the ready-made service rather than declaring one of its
     * own, as the "none" method has nothing to configure.
     */
    public function testAPublicClientCanUseTheReadyMadeNoClientAuthenticationService()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'security.oauth2.client_authentication.none',
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $this->assertSame(NoClientAuthentication::class, $container->getDefinition('security.oauth2.client_authentication.none')->getClass());
        $this->assertEquals(new Reference('security.oauth2.client_authentication.none'), $container->getDefinition('security.authenticator.oidc_login.client.main')->getArgument(3));
    }

    public function testTheClientSecretBasicMethodIsBuiltFromTheConfiguration()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['client_secret_basic' => 'my-client-secret'],
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $clientAuthentication = $container->getDefinition('security.authenticator.oidc_login.client_authentication.main');
        $this->assertInstanceOf(ChildDefinition::class, $clientAuthentication);
        $this->assertSame('security.oauth2.client_authentication.client_secret_basic', $clientAuthentication->getParent());
        $this->assertSame('my-client-secret', $clientAuthentication->getArgument(0));
        $this->assertEquals(
            new Reference('security.authenticator.oidc_login.client_authentication.main'),
            $container->getDefinition('security.authenticator.oidc_login.client.main')->getArgument(3),
        );
    }

    public function testTheClientSecretPostMethodIsBuiltFromTheConfiguration()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['client_secret_post' => 'my-client-secret'],
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $clientAuthentication = $container->getDefinition('security.authenticator.oidc_login.client_authentication.main');
        $this->assertSame('security.oauth2.client_authentication.client_secret_post', $clientAuthentication->getParent());
        $this->assertSame('my-client-secret', $clientAuthentication->getArgument(0));
    }

    public function testTheClientSecretJwtMethodIsBuiltFromTheConfiguration()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['client_secret_jwt' => ['secret' => 'my-client-secret', 'algorithm' => 'HS512', 'lifetime' => 30]],
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $clientAuthentication = $container->getDefinition('security.authenticator.oidc_login.client_authentication.main');
        $this->assertSame('security.oauth2.client_authentication.client_secret_jwt', $clientAuthentication->getParent());
        $this->assertSame('my-client-secret', $clientAuthentication->getArgument(0));
        $this->assertSame('HS512', $clientAuthentication->getArgument(1));
        $this->assertSame(30, $clientAuthentication->getArgument(2));
    }

    public function testTheClientSecretJwtMethodTakesTheSecretAlone()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['client_secret_jwt' => 'my-client-secret'],
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $clientAuthentication = $container->getDefinition('security.authenticator.oidc_login.client_authentication.main');
        $this->assertSame('my-client-secret', $clientAuthentication->getArgument(0));
        $this->assertSame('HS256', $clientAuthentication->getArgument(1));
        $this->assertSame(60, $clientAuthentication->getArgument(2));
    }

    /**
     * The key is configured as a JSON-encoded JWK, which an environment variable can carry,
     * and parsed by a definition of its own so that it stays a string until runtime.
     */
    public function testThePrivateKeyJwtMethodIsBuiltFromTheConfiguration()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['private_key_jwt' => ['key' => self::SIGNING_KEY, 'algorithm' => 'ES256', 'lifetime' => 30]],
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $clientAuthentication = $container->getDefinition('security.authenticator.oidc_login.client_authentication.main');
        $this->assertSame('security.oauth2.client_authentication.private_key_jwt', $clientAuthentication->getParent());
        $this->assertSame('ES256', $clientAuthentication->getArgument(1));
        $this->assertSame(30, $clientAuthentication->getArgument(2));

        $signingKey = $clientAuthentication->getArgument(0);
        $this->assertInstanceOf(ChildDefinition::class, $signingKey);
        $this->assertSame('security.oauth2.client_authentication.private_key_jwt.signing_key', $signingKey->getParent());
        $this->assertSame(self::SIGNING_KEY, $signingKey->getArgument(0));
    }

    public function testThePrivateKeyJwtMethodTakesTheKeyAlone()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['private_key_jwt' => self::SIGNING_KEY],
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $clientAuthentication = $container->getDefinition('security.authenticator.oidc_login.client_authentication.main');
        $this->assertSame(self::SIGNING_KEY, $clientAuthentication->getArgument(0)->getArgument(0));
        $this->assertSame('RS256', $clientAuthentication->getArgument(1));
        $this->assertSame(60, $clientAuthentication->getArgument(2));
    }

    /**
     * Each assertion method takes the algorithms it can be signed with and no other, so that
     * a shared secret never keys an asymmetric signature nor a private key a MAC.
     */
    #[DataProvider('provideAlgorithmsTheMethodDoesNotAllow')]
    public function testRejectsAnAlgorithmTheAssertionMethodDoesNotAllow(array $clientAuthentication, string $message)
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => $clientAuthentication,
        ], $factory);
    }

    public static function provideAlgorithmsTheMethodDoesNotAllow(): iterable
    {
        yield 'an asymmetric algorithm for client_secret_jwt' => [['client_secret_jwt' => ['secret' => 'my-client-secret', 'algorithm' => 'RS256']], 'The value "RS256" is not allowed for path "oidc-login.client_authentication.client_secret_jwt.algorithm". Permissible values: "HS256", "HS384", "HS512".'];
        yield 'a MAC algorithm for private_key_jwt' => [['private_key_jwt' => ['key' => self::SIGNING_KEY, 'algorithm' => 'HS256']], 'The value "HS256" is not allowed for path "oidc-login.client_authentication.private_key_jwt.algorithm". Permissible values: "RS256", "RS384", "RS512", "ES256", "ES384", "ES512", "PS256", "PS384", "PS512".'];
    }

    #[DataProvider('provideAssertionMethodsWithoutALifetime')]
    public function testRejectsAnAssertionLifetimeThatIsNotPositive(array $clientAuthentication, string $message)
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => $clientAuthentication,
        ], $factory);
    }

    public static function provideAssertionMethodsWithoutALifetime(): iterable
    {
        yield 'client_secret_jwt' => [['client_secret_jwt' => ['secret' => 'my-client-secret', 'lifetime' => 0]], 'The value 0 is too small for path "oidc-login.client_authentication.client_secret_jwt.lifetime". Should be greater than or equal to 1'];
        yield 'private_key_jwt' => [['private_key_jwt' => ['key' => self::SIGNING_KEY, 'lifetime' => 0]], 'The value 0 is too small for path "oidc-login.client_authentication.private_key_jwt.lifetime". Should be greater than or equal to 1'];
    }

    /**
     * Each firewall gets its own client authentication, as two of them authenticate at two
     * providers with two secrets.
     */
    public function testTwoFirewallsGetTwoClientAuthentications()
    {
        $container = new ContainerBuilder();

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['client_secret_basic' => 'main-secret'],
        ], $factory), 'userprovider');
        $factory->createAuthenticator($container, 'admin', $this->processConfig([
            'provider_uri' => 'https://other.example.com',
            'client_id' => 'my-other-client-id',
            'client_authentication' => ['client_secret_post' => 'admin-secret'],
        ], $factory), 'userprovider');

        $this->assertSame('main-secret', $container->getDefinition('security.authenticator.oidc_login.client_authentication.main')->getArgument(0));
        $this->assertSame('admin-secret', $container->getDefinition('security.authenticator.oidc_login.client_authentication.admin')->getArgument(0));
    }

    /**
     * "none" is the one method taking no parameter, so it is the one a bare string may name
     * without being taken for a service id.
     */
    public function testTheNoneShorthandDeclaresAPublicClient()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'none',
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $this->assertFalse($container->hasDefinition('security.authenticator.oidc_login.client_authentication.main'));
        $this->assertEquals(
            new Reference('security.oauth2.client_authentication.none'),
            $container->getDefinition('security.authenticator.oidc_login.client.main')->getArgument(3),
        );
    }

    public function testTheNoneMappingDeclaresAPublicClient()
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['none' => true],
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $this->assertEquals(
            new Reference('security.oauth2.client_authentication.none'),
            $container->getDefinition('security.authenticator.oidc_login.client.main')->getArgument(3),
        );
    }

    /**
     * Every shape the "client_authentication" node accepts, and the service each one ends up
     * injecting into the OIDC client.
     */
    #[DataProvider('provideClientAuthenticationShapes')]
    public function testEveryAcceptedShapeOfTheClientAuthenticationNode(array|string $clientAuthentication, string $expectedServiceId)
    {
        $container = new ContainerBuilder();

        $config = [
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => $clientAuthentication,
        ];

        $factory = new OidcLoginFactory();
        $factory->createAuthenticator($container, 'main', $this->processConfig($config, $factory), 'userprovider');

        $this->assertEquals(
            new Reference($expectedServiceId),
            $container->getDefinition('security.authenticator.oidc_login.client.main')->getArgument(3),
        );
    }

    public static function provideClientAuthenticationShapes(): iterable
    {
        $perFirewall = 'security.authenticator.oidc_login.client_authentication.main';

        yield 'client_secret_basic' => [['client_secret_basic' => 'my-client-secret'], $perFirewall];
        yield 'client_secret_post' => [['client_secret_post' => 'my-client-secret'], $perFirewall];
        yield 'client_secret_jwt' => [['client_secret_jwt' => 'my-client-secret'], $perFirewall];
        yield 'client_secret_jwt as a mapping' => [['client_secret_jwt' => ['secret' => 'my-client-secret', 'algorithm' => 'HS384']], $perFirewall];
        yield 'private_key_jwt' => [['private_key_jwt' => self::SIGNING_KEY], $perFirewall];
        yield 'private_key_jwt as a mapping' => [['private_key_jwt' => ['key' => self::SIGNING_KEY, 'algorithm' => 'ES256']], $perFirewall];
        yield 'none as a mapping' => [['none' => true], 'security.oauth2.client_authentication.none'];
        yield 'none as a null mapping' => [['none' => null], 'security.oauth2.client_authentication.none'];
        yield 'none as a string' => ['none', 'security.oauth2.client_authentication.none'];
        yield 'a service as a mapping' => [['id' => 'app.client_authentication'], 'app.client_authentication'];
        yield 'a service as a string' => ['app.client_authentication', 'app.client_authentication'];
    }

    public function testRejectsSeveralClientAuthenticationMethods()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Exactly one OIDC "client_authentication" method must be configured');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['client_secret_basic' => 'my-client-secret', 'none' => true],
        ], $factory);
    }

    public function testRejectsAnEmptyClientAuthenticationMapping()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Exactly one OIDC "client_authentication" method must be configured');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => [],
        ], $factory);
    }

    public function testRejectsAnEmptyClientSecret()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['client_secret_basic' => ''],
        ], $factory);
    }

    public function testRejectsTheNoneMethodSetToFalse()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "client_authentication.none" option only takes true');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['none' => false],
        ], $factory);
    }

    /**
     * The two rules a public client cannot bend are checked while the container compiles for
     * every method this bundle builds itself, so that the message names the firewall and a key
     * that can be grepped for in the configuration.
     */
    public function testAPublicClientCannotDisablePkceAtCompileTime()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "pkce.enabled" option cannot be false for a public client');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'none',
            'pkce' => ['enabled' => false],
        ], $factory);
    }

    public function testAPublicClientCannotDisableTheIdTokenSignatureCheckAtCompileTime()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The OIDC "id_token_signature.required" option cannot be false for a public client');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => ['none' => true],
            'id_token_signature' => ['required' => false],
        ], $factory);
    }

    /**
     * A service only reports its method once built, so the same two rules are left to the
     * constructor of the authenticator, which is what catches a custom implementation
     * authenticating with "none".
     */
    public function testAClientAuthenticationServiceLeavesTheTwoRulesToTheAuthenticator()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
            'pkce' => ['enabled' => false],
            'id_token_signature' => ['required' => false],
        ], $factory);

        $this->assertFalse($finalizedConfig['pkce']['enabled']);
        $this->assertFalse($finalizedConfig['id_token_signature']['required']);
    }

    public function testRejectsAMissingClientAuthentication()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('child config "client_authentication" under "oidc-login" must be configured');

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
        ], $factory);
    }

    public function testRejectsAnEmptyClientAuthentication()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => '',
        ], $factory);
    }

    public function testAConfidentialClientMayTurnTheIdTokenSignatureVerificationOff()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
            'id_token_signature' => ['required' => false],
        ], $factory);

        $this->assertFalse($finalizedConfig['id_token_signature']['required']);
    }

    public function testClaimsSourceAndUserIdentifierDefaults()
    {
        $factory = new OidcLoginFactory();

        $finalizedConfig = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
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
            'client_authentication' => 'app.client_authentication',
            'user_data_source' => 'id_token',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertSame(['authorization_endpoint', 'token_endpoint'], $container->getDefinition('security.authenticator.oidc_login.discovery.main')->getArgument(6));
    }

    public function testTokenRefresherIsAlwaysWired()
    {
        // the refresh token is kept on the security token whatever the configuration, so
        // an application renewing the access token by itself needs no option turned on
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $refresher = $container->getDefinition('security.authenticator.oidc_login.token_refresher.main');
        $this->assertEquals(new Reference('security.authenticator.oidc_login.client.main'), $refresher->getArgument(0));
        $this->assertEquals(new Reference('security.authenticator.oidc_login.discovery.main'), $refresher->getArgument(1));
        $this->assertEquals(new Reference('security.authenticator.oidc_login.id_token.main'), $refresher->getArgument(2));
        $this->assertSame('my-client-id', $refresher->getArgument(3));
        $this->assertEquals(new Reference('security.authenticator.oidc_login.signature_verifier.main'), $refresher->getArgument(4));
        $this->assertSame(30, $refresher->getArgument(5));
    }

    public function testTokenRefresherVerifiesNoSignatureWhenTheAuthenticatorDoesNot()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
            'id_token_signature' => ['required' => false],
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertNull($container->getDefinition('security.authenticator.oidc_login.token_refresher.main')->getArgument(4));
    }

    public function testTokenRefreshListenerIsNotRegisteredByDefault()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertSame([], $factory->createListeners($container, 'main', $config));
        $this->assertFalse($container->hasDefinition('security.authenticator.oidc_login.token_refresh_listener.main'));
    }

    public function testTokenRefreshListenerIsRegisteredWhenEnabled()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
            'refresh_access_token' => ['enabled' => true, 'leeway' => 60],
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertSame(['security.authenticator.oidc_login.token_refresh_listener.main'], $factory->createListeners($container, 'main', $config));

        $listener = $container->getDefinition('security.authenticator.oidc_login.token_refresh_listener.main');
        $this->assertEquals(new Reference('security.authenticator.oidc_login.token_refresher.main'), $listener->getArgument(1));
        $this->assertSame(60, $container->getDefinition('security.authenticator.oidc_login.token_refresher.main')->getArgument(5));
    }

    public function testRejectsANegativeLeeway()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
            'refresh_access_token' => ['enabled' => true, 'leeway' => -1],
        ], $factory);
    }

    public function testTheConfiguredHttpClientIsUsedForEveryCallToTheProvider()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
            'http_client' => 'oidc.client',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertEquals(new Reference('oidc.client'), $container->getDefinition('security.authenticator.oidc_login.discovery.main')->getArgument(0));
        $this->assertEquals(new Reference('oidc.client'), $container->getDefinition('security.authenticator.oidc_login.client.main')->getArgument(0));
        $this->assertEquals(new Reference('oidc.client'), $container->getDefinition('security.authenticator.oidc_login.signature_verifier.main')->getArgument(2));
    }

    public function testTheConfiguredHttpClientIsUsedWhenTheIdTokenSignatureIsNotVerified()
    {
        $container = new ContainerBuilder();
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
            'id_token_signature' => ['required' => false],
            'http_client' => 'oidc.client',
        ], $factory);
        $factory->createAuthenticator($container, 'main', $config, 'userprovider');

        $this->assertFalse($container->hasDefinition('security.authenticator.oidc_login.signature_verifier.main'));
        $this->assertEquals(new Reference('oidc.client'), $container->getDefinition('security.authenticator.oidc_login.discovery.main')->getArgument(0));
        $this->assertEquals(new Reference('oidc.client'), $container->getDefinition('security.authenticator.oidc_login.client.main')->getArgument(0));
    }

    public function testTheHttpClientDefaultsToNull()
    {
        $factory = new OidcLoginFactory();

        $config = $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
        ], $factory);

        $this->assertNull($config['http_client']);
    }

    public function testRejectsAnEmptyHttpClient()
    {
        $factory = new OidcLoginFactory();

        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'provider_uri' => 'https://provider.example.com',
            'client_id' => 'my-client-id',
            'client_authentication' => 'app.client_authentication',
            'http_client' => '',
        ], $factory);
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

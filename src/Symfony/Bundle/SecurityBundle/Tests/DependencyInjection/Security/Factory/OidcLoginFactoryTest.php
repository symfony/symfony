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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\OidcLoginFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login.state_storage.main'));
        $this->assertTrue($container->hasDefinition('security.authenticator.oidc_login.client.main'));
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
        ];

        $factory = new OidcLoginFactory();
        $finalizedConfig = $this->processConfig($config, $factory);

        $this->assertSame('/login_check', $finalizedConfig['check_path']);
        $this->assertSame('/login', $finalizedConfig['login_path']);
        $this->assertSame(['openid'], $finalizedConfig['scopes']);
        $this->assertSame('sub', $finalizedConfig['claim']);
        $this->assertFalse($finalizedConfig['direct_redirect']);
        $this->assertTrue($finalizedConfig['enable_userinfo']);
        $this->assertTrue($finalizedConfig['pkce']['enabled']);
        $this->assertSame('S256', $finalizedConfig['pkce']['method']);
        $this->assertSame('client_secret_post', $finalizedConfig['token_endpoint_auth_method']);
        $this->assertFalse($finalizedConfig['enable_end_session']);
        $this->assertSame(3600, $finalizedConfig['discovery_cache_ttl']);
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

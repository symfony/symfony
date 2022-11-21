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
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OidcTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OidcUserInfoTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\ServiceTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AccessTokenFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AccessTokenFactoryTest extends TestCase
{
    public function testBasicServiceConfiguration()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => 'in_memory_token_handler_service_id',
            'success_handler' => 'success_handler_service_id',
            'failure_handler' => 'failure_handler_service_id',
            'token_extractors' => ['BAR', 'FOO'],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
    }

    public function testDefaultTokenHandlerConfiguration()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => 'in_memory_token_handler_service_id',
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));
    }

    public function testIdTokenHandlerConfiguration()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => ['id' => 'in_memory_token_handler_service_id'],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));
    }

    public function testOidcUserInfoTokenHandlerConfigurationWithExistingClient()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => ['oidc_user_info' => ['client' => 'oidc.client']],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));
        $this->assertFalse($container->hasDefinition('http_client.security.access_token_handler.oidc_user_info'));
    }

    public function testOidcUserInfoTokenHandlerConfigurationWithClientCreation()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => ['oidc_user_info' => ['client' => ['base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo']]],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));
        $this->assertTrue($container->hasDefinition('http_client.security.access_token_handler.oidc_user_info'));
    }

    public function testMultipleTokenHandlersSet()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You cannot configure multiple token handlers.');

        $config = [
            'token_handler' => [
                'id' => 'in_memory_token_handler_service_id',
                'oidc_user_info' => ['client' => 'oidc.client'],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $this->processConfig($config, $factory);
    }

    public function testNoTokenHandlerSet()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must set a token handler.');

        $config = [
            'token_handler' => [],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $this->processConfig($config, $factory);
    }

    public function testNoExtractorsDefined()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "access_token.token_extractors" should have at least 1 element(s) defined.');
        $config = [
            'token_handler' => 'in_memory_token_handler_service_id',
            'success_handler' => 'success_handler_service_id',
            'failure_handler' => 'failure_handler_service_id',
            'token_extractors' => [],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $this->processConfig($config, $factory);
    }

    public function testNoHandlerDefined()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "token_handler" under "access_token" must be configured.');
        $config = [
            'success_handler' => 'success_handler_service_id',
            'failure_handler' => 'failure_handler_service_id',
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $this->processConfig($config, $factory);
    }

    private function processConfig(array $config, AccessTokenFactory $factory)
    {
        $nodeDefinition = new ArrayNodeDefinition('access_token');
        $factory->addConfiguration($nodeDefinition);

        $node = $nodeDefinition->getNode();
        $normalizedConfig = $node->normalize($config);

        return $node->finalize($normalizedConfig);
    }

    private function createTokenHandlerFactories(): array
    {
        return [
            new ServiceTokenHandlerFactory(),
            new OidcUserInfoTokenHandlerFactory(),
            new OidcTokenHandlerFactory(),
        ];
    }
}

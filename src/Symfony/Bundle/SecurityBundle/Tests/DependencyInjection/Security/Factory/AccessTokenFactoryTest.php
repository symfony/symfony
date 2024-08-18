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
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\CasTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OidcTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OidcUserInfoTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\ServiceTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AccessTokenFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

    public function testCasTokenHandlerConfiguration()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => ['cas' => ['validation_url' => 'https://www.example.com/cas/validate']],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.access_token_handler.cas'));

        $arguments = $container->getDefinition('security.access_token_handler.cas')->getArguments();
        $this->assertSame((string) $arguments[0], 'request_stack');
        $this->assertSame($arguments[1], 'https://www.example.com/cas/validate');
        $this->assertSame($arguments[2], 'cas');
        $this->assertNull($arguments[3]);
    }

    public function testInvalidOidcTokenHandlerConfigurationKeyMissing()
    {
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithm' => 'RS256',
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "keyset" under "access_token.token_handler.oidc" must be configured: JSON-encoded JWKSet used to sign the token (must contain a list of valid keys).');

        $this->processConfig($config, $factory);
    }

    public function testInvalidOidcTokenHandlerConfigurationDuplicatedKeyParameters()
    {
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithm' => 'RS256',
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'key' => 'key',
                    'keyset' => 'keyset',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You cannot use both "key" and "keyset" at the same time.');

        $this->processConfig($config, $factory);
    }

    public function testInvalidOidcTokenHandlerConfigurationDuplicatedAlgorithmParameters()
    {
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithm' => 'RS256',
                    'algorithms' => ['RS256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => 'keyset',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You cannot use both "algorithm" and "algorithms" at the same time.');

        $this->processConfig($config, $factory);
    }

    public function testInvalidOidcTokenHandlerConfigurationMissingAlgorithmParameters()
    {
        $config = [
            'token_handler' => [
                'oidc' => [
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => 'keyset',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "algorithms" under "access_token.token_handler.oidc" must be configured: Algorithms used to sign the token.');

        $this->processConfig($config, $factory);
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Since symfony/security-bundle 7.1: The "key" option is deprecated and will be removed in 8.0. Use the "keyset" option instead.
     */
    public function testOidcTokenHandlerConfigurationWithSingleAlgorithm()
    {
        $container = new ContainerBuilder();
        $jwk = '{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithm' => 'RS256',
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'key' => $jwk,
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));

        $expected = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc.signature'))
                ->replaceArgument(0, ['RS256']),
            'index_1' => (new ChildDefinition('security.access_token_handler.oidc.jwkset'))
                ->replaceArgument(0, \sprintf('{"keys":[%s]}', $jwk)),
            'index_2' => 'audience',
            'index_3' => ['https://www.example.com'],
            'index_4' => 'sub',
        ];
        $this->assertEquals($expected, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
    }

    public function testOidcTokenHandlerConfigurationWithMultipleAlgorithms()
    {
        $container = new ContainerBuilder();
        $jwkset = '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"},{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}]}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => $jwkset,
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));

        $expected = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc.signature'))
                ->replaceArgument(0, ['RS256', 'ES256']),
            'index_1' => (new ChildDefinition('security.access_token_handler.oidc.jwkset'))
                ->replaceArgument(0, $jwkset),
            'index_2' => 'audience',
            'index_3' => ['https://www.example.com'],
            'index_4' => 'sub',
        ];
        $this->assertEquals($expected, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
    }

    public function testOidcUserInfoTokenHandlerConfigurationWithExistingClient()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oidc_user_info' => [
                    'base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo',
                    'client' => 'oidc.client',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));

        $expected = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc_user_info.http_client'))
                ->setFactory([new Reference('oidc.client'), 'withOptions'])
                ->replaceArgument(0, ['base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo']),
            'index_2' => 'sub',
        ];
        $this->assertEquals($expected, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
    }

    /**
     * @dataProvider getOidcUserInfoConfiguration
     */
    public function testOidcUserInfoTokenHandlerConfigurationWithBaseUri(array|string $configuration)
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => ['oidc_user_info' => $configuration],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));

        $expected = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc_user_info.http_client'))
                ->replaceArgument(0, ['base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo']),
            'index_2' => 'sub',
        ];

        if (!interface_exists(HttpClientInterface::class)) {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('You cannot use the "oidc_user_info" token handler since the HttpClient component is not installed. Try running "composer require symfony/http-client".');
        }

        $this->assertEquals($expected, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
    }

    public static function getOidcUserInfoConfiguration(): iterable
    {
        yield [['base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo']];
        yield ['https://www.example.com/realms/demo/protocol/openid-connect/userinfo'];
    }

    public function testMultipleTokenHandlersSet()
    {
        $config = [
            'token_handler' => [
                'id' => 'in_memory_token_handler_service_id',
                'oidc_user_info' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo',
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You cannot configure multiple token handlers.');

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
            new CasTokenHandlerFactory(),
        ];
    }
}

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

use Jose\Component\Core\AlgorithmManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Controller\ProtectedResourceMetadataController;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\CasTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OAuth2TokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OidcTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OidcUserInfoTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\ServiceTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AccessTokenFactory;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenGenerator;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenHandler;
use Symfony\Contracts\Cache\CacheInterface;
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
                    'algorithms' => ['RS256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must set either "discovery" or "keyset".');

        $this->processConfig($config, $factory);
    }

    public function testOidcTokenHandlerConfigurationDefaultsToRs256()
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
        $finalizedConfig = $this->processConfig($config, $factory);

        $this->assertSame(['RS256'], $finalizedConfig['token_handler']['oidc']['algorithms']);
    }

    public function testOidcTokenHandlerConfigurationWithMultipleAlgorithms()
    {
        $container = new ContainerBuilder();
        $jwkset = '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"},{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}]}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'enforce_at_jwt_type' => false,
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
            'index_7' => 0,
            'index_8' => false,
        ];
        $this->assertEquals($expected, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());

        // Assert that the handler does NOT have the kernel.reset tag when discovery is NOT enabled
        $tags = $container->getDefinition('security.access_token_handler.firewall1')->getTags();
        $this->assertArrayNotHasKey('kernel.reset', $tags);
    }

    public function testOidcTokenHandlerConfigurationWithSeveralAudiences()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oidc' => [
                    'enforce_at_jwt_type' => true,
                    'issuers' => ['https://www.example.com'],
                    'audience' => ['https://api.example.com', 'https://admin.example.com'],
                    'keyset' => '{"keys":[]}',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertSame(['https://api.example.com', 'https://admin.example.com'], $container->getDefinition('security.access_token_handler.firewall1')->getArgument(2));
    }

    public function testOidcTokenHandlerConfigurationWithEncryption()
    {
        $container = new ContainerBuilder();
        $jwkset = '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"},{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}]}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'enforce_at_jwt_type' => false,
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => $jwkset,
                    'encryption' => [
                        'enabled' => true,
                        'keyset' => $jwkset,
                        'algorithms' => ['RSA-OAEP', 'RSA1_5'],
                    ],
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));
    }

    public function testInvalidOidcTokenHandlerConfigurationMissingEncryptionKeyset()
    {
        $jwkset = '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"},{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}]}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => $jwkset,
                    'encryption' => [
                        'enabled' => true,
                        'algorithms' => ['RSA-OAEP', 'RSA1_5'],
                    ],
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "keyset" under "access_token.token_handler.oidc.encryption" must be configured: JSON-encoded JWKSet used to decrypt the token (must contain a list of valid private keys).');

        $this->processConfig($config, $factory);
    }

    public function testInvalidOidcTokenHandlerConfigurationMissingAlgorithm()
    {
        $jwkset = '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"},{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}]}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => $jwkset,
                    'encryption' => [
                        'enabled' => true,
                        'keyset' => $jwkset,
                        'algorithms' => [],
                    ],
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "access_token.token_handler.oidc.encryption.algorithms" should have at least 1 element(s) defined.');

        $this->processConfig($config, $factory);
    }

    public function testOidcTokenHandlerConfigurationWithDiscovery()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oidc' => [
                    'enforce_at_jwt_type' => false,
                    'discovery' => [
                        'base_uri' => 'https://www.example.com/realms/demo/',
                        'cache' => [
                            'id' => 'oidc_cache',
                        ],
                    ],
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));

        $expectedArgs = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc.signature'))
                ->replaceArgument(0, ['RS256', 'ES256']),
            'index_1' => null,
            'index_2' => 'audience',
            'index_3' => ['https://www.example.com'],
            'index_4' => 'sub',
            'index_7' => 0,
            'index_8' => false,
        ];
        $expectedCalls = [
            [
                'enableDiscovery',
                [
                    new Reference('oidc_cache'),
                    [
                        (new ChildDefinition('security.access_token_handler.oidc_discovery.http_client'))
                        ->replaceArgument(0, ['base_uri' => 'https://www.example.com/realms/demo/']),
                    ],
                    'security.access_token_handler.firewall1.oidc_configuration',
                    true,
                ],
            ],
        ];
        $this->assertEquals($expectedArgs, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
        $this->assertEquals($expectedCalls, $container->getDefinition('security.access_token_handler.firewall1')->getMethodCalls());

        // Assert that the handler has the kernel.reset tag when discovery is enabled
        $tags = $container->getDefinition('security.access_token_handler.firewall1')->getTags();
        $this->assertArrayHasKey('kernel.reset', $tags);
        $this->assertSame(['method' => 'reset'], $tags['kernel.reset'][0]);
    }

    public function testOidcTokenHandlerConfigurationWithMultipleDiscoveryBaseUri()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oidc' => [
                    'enforce_at_jwt_type' => false,
                    'discovery' => [
                        'base_uri' => [
                            'https://www.example.com/realms/demo/',
                            'https://www.api.com/realms/api/',
                        ],
                        'cache' => [
                            'id' => 'oidc_cache',
                        ],
                    ],
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));

        $expectedArgs = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc.signature'))
                ->replaceArgument(0, ['RS256', 'ES256']),
            'index_1' => null,
            'index_2' => 'audience',
            'index_3' => ['https://www.example.com'],
            'index_4' => 'sub',
            'index_7' => 0,
            'index_8' => false,
        ];
        $expectedCalls = [
            [
                'enableDiscovery',
                [
                    new Reference('oidc_cache'),
                    [
                        (new ChildDefinition('security.access_token_handler.oidc_discovery.http_client'))
                        ->replaceArgument(0, ['base_uri' => 'https://www.example.com/realms/demo/']),
                        (new ChildDefinition('security.access_token_handler.oidc_discovery.http_client'))
                            ->replaceArgument(0, ['base_uri' => 'https://www.api.com/realms/api/']),
                    ],
                    'security.access_token_handler.firewall1.oidc_configuration',
                    true,
                ],
            ],
        ];
        $this->assertEquals($expectedArgs, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
        $this->assertEquals($expectedCalls, $container->getDefinition('security.access_token_handler.firewall1')->getMethodCalls());

        // Assert that the handler has the kernel.reset tag when discovery is enabled
        $tags = $container->getDefinition('security.access_token_handler.firewall1')->getTags();
        $this->assertArrayHasKey('kernel.reset', $tags);
        $this->assertSame(['method' => 'reset'], $tags['kernel.reset'][0]);
    }

    #[DataProvider('provideEnforceKeyUsageVerification')]
    public function testOidcTokenHandlerEnableDiscoveryArgsMatchMethodSignature(bool $enforceKeyUsageVerification)
    {
        if (!class_exists(OidcTokenHandler::class)) {
            $this->markTestSkipped('OidcTokenHandler not available.');
        }
        if (!interface_exists(HttpClientInterface::class)) {
            $this->markTestSkipped('HttpClient component not available.');
        }

        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oidc' => [
                    'enforce_at_jwt_type' => false,
                    'discovery' => [
                        'base_uri' => 'https://www.example.com/realms/demo/',
                        'cache' => ['id' => 'oidc_cache'],
                        'enforce_key_usage_verification' => $enforceKeyUsageVerification,
                    ],
                    'algorithms' => ['RS256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $methodCalls = $container->getDefinition('security.access_token_handler.firewall1')->getMethodCalls();
        $this->assertSame('enableDiscovery', $methodCalls[0][0]);

        $reflection = new \ReflectionMethod(OidcTokenHandler::class, 'enableDiscovery');
        $this->assertLessThanOrEqual($reflection->getNumberOfParameters(), \count($methodCalls[0][1]), 'Recorded enableDiscovery call must not pass more arguments than the method accepts.');

        $handler = new OidcTokenHandler(new AlgorithmManager([]), null, 'audience', ['https://www.example.com'], 'sub', null, new Clock(), 0, true);
        $cache = $this->createStub(CacheInterface::class);
        $httpClient = $this->createStub(HttpClientInterface::class);
        $callArgs = $methodCalls[0][1];
        $callArgs[0] = $cache;
        $callArgs[1] = [$httpClient];

        $handler->enableDiscovery(...$callArgs);

        $reflectedProperty = new \ReflectionProperty(OidcTokenHandler::class, 'enforceKeyUsageVerification');
        $this->assertSame($enforceKeyUsageVerification, $reflectedProperty->getValue($handler));
    }

    public static function provideEnforceKeyUsageVerification(): iterable
    {
        yield 'enforced' => [true];
        yield 'not enforced' => [false];
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

    #[DataProvider('getOidcUserInfoConfiguration')]
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

        // Assert that the handler does NOT have the kernel.reset tag when discovery is NOT enabled
        $tags = $container->getDefinition('security.access_token_handler.firewall1')->getTags();
        $this->assertArrayNotHasKey('kernel.reset', $tags);
    }

    public static function getOidcUserInfoConfiguration(): iterable
    {
        yield [['base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo']];
        yield ['https://www.example.com/realms/demo/protocol/openid-connect/userinfo'];
    }

    public function testOidcUserInfoTokenHandlerConfigurationWithDiscovery()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oidc_user_info' => [
                    'discovery' => [
                        'cache' => [
                            'id' => 'oidc_cache',
                        ],
                    ],
                    'base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));

        $expectedArgs = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc_user_info.http_client'))
                ->replaceArgument(0, ['base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo']),
            'index_2' => 'sub',
        ];
        $expectedCalls = [
            [
                'enableDiscovery',
                [
                    new Reference('oidc_cache'),
                    'security.access_token_handler.firewall1.oidc_configuration',
                ],
            ],
        ];
        $this->assertEquals($expectedArgs, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
        $this->assertEquals($expectedCalls, $container->getDefinition('security.access_token_handler.firewall1')->getMethodCalls());

        // Assert that the handler has the kernel.reset tag when discovery is enabled
        $tags = $container->getDefinition('security.access_token_handler.firewall1')->getTags();
        $this->assertArrayHasKey('kernel.reset', $tags);
        $this->assertSame(['method' => 'reset'], $tags['kernel.reset'][0]);
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

    public function testOAuth2TokenHandlerConfiguration()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => ['oauth2' => true],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertTrue($container->hasDefinition('security.access_token_handler.firewall1'));

        $this->assertEquals([
            'index_2' => [],
            'index_3' => null,
            'index_4' => null,
            'index_6' => 0,
        ], $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
    }

    public function testOAuth2TokenHandlerConfigurationWithAScopedClient()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oauth2' => [
                    'http_client' => 'oauth2.introspection',
                    'audience' => 'https://api.example.com',
                    'issuer' => 'https://www.example.com',
                    'claim' => 'username',
                    'allowed_time_drift' => 5,
                    'cache' => ['id' => 'oauth2_cache'],
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $definition = $container->getDefinition('security.access_token_handler.firewall1');
        $this->assertEquals([
            'index_0' => new Reference('oauth2.introspection'),
            'index_2' => 'https://api.example.com',
            'index_3' => 'https://www.example.com',
            'index_4' => 'username',
            'index_6' => 5,
        ], $definition->getArguments());
        $this->assertEquals([
            ['enableCache', [new Reference('oauth2_cache'), 'security.access_token_handler.firewall1.introspection.', 60]],
        ], $definition->getMethodCalls());
    }

    public function testOAuth2TokenHandlerConfigurationWithSeveralAudiences()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oauth2' => [
                    'audience' => ['https://api.example.com', 'https://admin.example.com'],
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertSame(['https://api.example.com', 'https://admin.example.com'], $container->getDefinition('security.access_token_handler.firewall1')->getArgument(2));
    }

    public function testOAuth2TokenHandlerConfigurationWithASignedResponse()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oauth2' => [
                    'audience' => 'https://api.example.com',
                    'issuer' => 'https://www.example.com',
                    'response_signature' => [
                        'enabled' => true,
                        'algorithms' => ['RS256'],
                        'keyset' => '{"keys":[]}',
                    ],
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertEquals([
            [
                'enableSignedResponse',
                [
                    (new ChildDefinition('security.access_token_handler.oidc.signature'))->replaceArgument(0, ['RS256']),
                    (new ChildDefinition('security.access_token_handler.oidc.jwkset'))->replaceArgument(0, '{"keys":[]}'),
                    true,
                ],
            ],
        ], $container->getDefinition('security.access_token_handler.firewall1')->getMethodCalls());
    }

    public function testOAuth2TokenHandlerConfigurationWithASignedResponseDisabled()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oauth2' => [
                    'http_client' => 'oauth2.client',
                    'response_signature' => false,
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertSame([], $container->getDefinition('security.access_token_handler.firewall1')->getMethodCalls());
    }

    public function testOAuth2TokenHandlerConfigurationSignedResponseDefaultsToRs256()
    {
        $config = [
            'token_handler' => [
                'oauth2' => [
                    'http_client' => 'oauth2.client',
                    'issuer' => 'https://authorization-server.example.com/',
                    'audience' => 'https://api.example.com',
                    'response_signature' => [
                        'enabled' => true,
                        'keyset' => '{"keys":[]}',
                    ],
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $this->assertSame(['RS256'], $finalizedConfig['token_handler']['oauth2']['response_signature']['algorithms']);
    }

    public function testOAuth2TokenHandlerConfigurationWithASignedResponseAndNoIssuer()
    {
        $config = [
            'token_handler' => [
                'oauth2' => [
                    'audience' => 'https://api.example.com',
                    'response_signature' => [
                        'enabled' => true,
                        'algorithms' => ['RS256'],
                        'keyset' => '{"keys":[]}',
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "issuer" and "audience" options of the "oauth2" token handler are required when "response_signature" is enabled: RFC 9701 §5 makes "iss" and "aud" mandatory claims of the response.');

        $this->processConfig($config, new AccessTokenFactory($this->createTokenHandlerFactories()));
    }

    public function testOAuth2TokenHandlerConfigurationWithAClientAsAString()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => ['oauth2' => 'oauth2.introspection'],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertEquals(new Reference('oauth2.introspection'), $container->getDefinition('security.access_token_handler.firewall1')->getArgument(0));
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

    public function testNoResourceMetadataIsServedByDefault()
    {
        $container = $this->createContainerBuilder();
        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $config = $this->processConfig(['token_handler' => 'in_memory_token_handler_service_id'], $factory);

        $factory->createAuthenticator($container, 'firewall1', $config, 'userprovider');

        $this->assertNull($container->getDefinition('security.authenticator.access_token.firewall1')->getArgument(6));
        $this->assertSame([], $container->getParameter('security.access_token.resource_metadata_paths'));
        $this->assertSame([], $container->getDefinition('security.authenticator.access_token.protected_resource_metadata_controller')->getArgument(0));
    }

    public function testResourceMetadataIsServedAtTheWellKnownPath()
    {
        $container = $this->createContainerBuilder();
        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $config = $this->processConfig([
            'token_handler' => 'in_memory_token_handler_service_id',
            'resource_metadata' => [
                'authorization_servers' => 'https://accounts.example.com',
                'scopes_supported' => ['profile', 'email'],
                'resource_name' => 'My API',
            ],
        ], $factory);

        $factory->createAuthenticator($container, 'firewall1', $config, 'userprovider');

        // without a "resource", the URL can only be resolved against the incoming request
        $this->assertSame('/.well-known/oauth-protected-resource', $container->getDefinition('security.authenticator.access_token.firewall1')->getArgument(6));
        $this->assertSame(['firewall1' => '/.well-known/oauth-protected-resource'], $container->getParameter('security.access_token.resource_metadata_paths'));
        $this->assertSame(['firewall1' => [
            'authorization_servers' => ['https://accounts.example.com'],
            'scopes_supported' => ['profile', 'email'],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'My API',
        ]], $container->getDefinition('security.authenticator.access_token.protected_resource_metadata_controller')->getArgument(0));
    }

    public function testTheResourcePathIsInsertedAfterTheWellKnownPath()
    {
        $container = $this->createContainerBuilder();
        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $config = $this->processConfig([
            'token_handler' => 'in_memory_token_handler_service_id',
            'resource_metadata' => ['resource' => 'https://api.example.com:8443/v1/'],
        ], $factory);

        $factory->createAuthenticator($container, 'firewall1', $config, 'userprovider');

        $this->assertSame('https://api.example.com:8443/.well-known/oauth-protected-resource/v1', $container->getDefinition('security.authenticator.access_token.firewall1')->getArgument(6));
        $this->assertSame(['firewall1' => '/.well-known/oauth-protected-resource/v1'], $container->getParameter('security.access_token.resource_metadata_paths'));
        $this->assertSame(['firewall1' => [
            'resource' => 'https://api.example.com:8443/v1/',
            'bearer_methods_supported' => ['header'],
        ]], $container->getDefinition('security.authenticator.access_token.protected_resource_metadata_controller')->getArgument(0));
    }

    #[DataProvider('provideBearerMethods')]
    public function testBearerMethodsAreDeducedFromTheTokenExtractors(array $extractors, array $expected)
    {
        $container = $this->createContainerBuilder();
        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $config = $this->processConfig([
            'token_handler' => 'in_memory_token_handler_service_id',
            'token_extractors' => $extractors,
            'resource_metadata' => [],
        ], $factory);

        $factory->createAuthenticator($container, 'firewall1', $config, 'userprovider');

        $metadata = $container->getDefinition('security.authenticator.access_token.protected_resource_metadata_controller')->getArgument(0)['firewall1'];
        $this->assertSame($expected, $metadata['bearer_methods_supported'] ?? []);
    }

    public static function provideBearerMethods(): iterable
    {
        yield 'aliases' => [['header', 'request_body', 'query_string'], ['header', 'body', 'query']];
        yield 'service ids' => [['security.access_token_extractor.query_string'], ['query']];
        yield 'the same method twice' => [['header', 'security.access_token_extractor.header'], ['header']];
        yield 'a custom extractor names no method' => [['custom_extractor_service_id'], []];
        yield 'a custom extractor next to a known one' => [['custom_extractor_service_id', 'header'], ['header']];
    }

    public function testConfiguredBearerMethodsWinOverTheDeducedOnes()
    {
        $container = $this->createContainerBuilder();
        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $config = $this->processConfig([
            'token_handler' => 'in_memory_token_handler_service_id',
            'token_extractors' => ['custom_extractor_service_id'],
            'resource_metadata' => ['bearer_methods_supported' => 'header'],
        ], $factory);

        $factory->createAuthenticator($container, 'firewall1', $config, 'userprovider');

        $metadata = $container->getDefinition('security.authenticator.access_token.protected_resource_metadata_controller')->getArgument(0)['firewall1'];
        $this->assertSame(['header'], $metadata['bearer_methods_supported']);
    }

    public function testEachFirewallGetsItsOwnMetadata()
    {
        $container = $this->createContainerBuilder();
        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());

        foreach (['firewall1' => 'https://example.com/api', 'firewall2' => 'https://example.com/admin'] as $firewallName => $resource) {
            $config = $this->processConfig([
                'token_handler' => 'in_memory_token_handler_service_id',
                'resource_metadata' => ['resource' => $resource],
            ], $factory);

            $factory->createAuthenticator($container, $firewallName, $config, 'userprovider');
        }

        $this->assertSame([
            'firewall1' => '/.well-known/oauth-protected-resource/api',
            'firewall2' => '/.well-known/oauth-protected-resource/admin',
        ], $container->getParameter('security.access_token.resource_metadata_paths'));
        $this->assertSame(['firewall1', 'firewall2'], array_keys($container->getDefinition('security.authenticator.access_token.protected_resource_metadata_controller')->getArgument(0)));
    }

    #[DataProvider('provideInvalidResourceIdentifiers')]
    public function testInvalidResourceIdentifier(string $resource)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The protected resource "resource" identifier must be an HTTPS URL without a fragment');

        $this->processConfig([
            'token_handler' => 'in_memory_token_handler_service_id',
            'resource_metadata' => ['resource' => $resource],
        ], new AccessTokenFactory($this->createTokenHandlerFactories()));
    }

    public static function provideInvalidResourceIdentifiers(): iterable
    {
        // the well-known path is derived from the path component of the identifier when the
        // route is declared, so a value the container cannot parse cannot be served
        yield 'an environment variable used as the whole value' => ['env_2b0d1a_API_RESOURCE_4f8c'];
        yield 'not HTTPS' => ['http://api.example.com'];
        yield 'with a fragment' => ['https://api.example.com/v1#api'];
        yield 'no URL at all' => ['https://api.example.com:port/v1'];
    }

    #[DataProvider('provideValidResourceIdentifiers')]
    public function testValidResourceIdentifier(string $resource, string $expectedPath, string $expectedUri)
    {
        $container = $this->createContainerBuilder();
        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $config = $this->processConfig([
            'token_handler' => 'in_memory_token_handler_service_id',
            'resource_metadata' => ['resource' => $resource],
        ], $factory);

        $factory->createAuthenticator($container, 'firewall1', $config, 'userprovider');

        $this->assertSame($expectedPath, $container->getParameter('security.access_token.resource_metadata_paths')['firewall1']);
        $this->assertSame($expectedUri, $container->getDefinition('security.authenticator.access_token.firewall1')->getArgument(6));
    }

    public static function provideValidResourceIdentifiers(): iterable
    {
        yield 'HTTPS' => ['https://api.example.com', '/.well-known/oauth-protected-resource', 'https://api.example.com/.well-known/oauth-protected-resource'];
        yield 'a loopback host over HTTP' => ['http://127.0.0.1:8000/api', '/.well-known/oauth-protected-resource/api', 'http://127.0.0.1:8000/.well-known/oauth-protected-resource/api'];
        yield 'a host reserved for testing' => ['http://api.example.test', '/.well-known/oauth-protected-resource', 'http://api.example.test/.well-known/oauth-protected-resource'];
        // a query is only a SHOULD NOT, and RFC 9728, Section 3.1 keeps it after the path
        yield 'with a query' => ['https://api.example.com/v1?tenant=acme', '/.well-known/oauth-protected-resource/v1', 'https://api.example.com/.well-known/oauth-protected-resource/v1?tenant=acme'];
        // an environment variable interpolated into the URL still leaves the path readable
        yield 'an environment variable inside a URL' => ['https://env_2b0d1a_API_HOST_4f8c/v1', '/.well-known/oauth-protected-resource/v1', 'https://env_2b0d1a_API_HOST_4f8c/.well-known/oauth-protected-resource/v1'];
    }

    private function createContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('security.access_token.resource_metadata_paths', []);
        $container->register('security.authenticator.access_token.protected_resource_metadata_controller', ProtectedResourceMetadataController::class)
            ->setArguments([[]]);

        return $container;
    }

    private function createTokenHandlerFactories(): array
    {
        return [
            new ServiceTokenHandlerFactory(),
            new OidcUserInfoTokenHandlerFactory(),
            new OidcTokenHandlerFactory(),
            new CasTokenHandlerFactory(),
            new OAuth2TokenHandlerFactory(),
        ];
    }

    public function testOidcTokenGenerator()
    {
        if (!class_exists(OidcTokenGenerator::class)) {
            $this->markTestSkipped('OidcTokenGenerator not available.');
        }

        $container = new ContainerBuilder();
        $jwkset = '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"},{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}]}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'enforce_at_jwt_type' => false,
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

        $this->assertTrue($container->hasDefinition('security.access_token_handler.oidc.command.generate'));
        $this->assertTrue($container->getDefinition('security.access_token_handler.oidc.command.generate')->hasMethodCall('addGenerator'));
    }

    public function testOidcTokenGeneratorCommandWithNoTokenHandler()
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

        $this->assertFalse($container->hasDefinition('security.access_token_handler.oidc.command.generate'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNotSettingTheAtJwtTypeEnforcementIsDeprecated()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"}]}',
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $this->expectUserDeprecationMessage('Since symfony/security-bundle 8.2: Not setting the "enforce_at_jwt_type" option of the "oidc" token handler is deprecated, set it explicitly; it will default to true in 9.0.');

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertFalse($container->getDefinition('security.access_token_handler.firewall1')->getArgument(8));
    }

    public function testOidcTokenHandlerConfigurationWithEnforcedAtJwtType()
    {
        $container = new ContainerBuilder();
        $jwkset = '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"}]}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => $jwkset,
                    'enforce_at_jwt_type' => true,
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $expected = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc.signature'))
                ->replaceArgument(0, ['RS256', 'ES256']),
            'index_1' => (new ChildDefinition('security.access_token_handler.oidc.jwkset'))
                ->replaceArgument(0, $jwkset),
            'index_2' => 'audience',
            'index_3' => ['https://www.example.com'],
            'index_4' => 'sub',
            'index_7' => 0,
            'index_8' => true,
        ];
        $this->assertEquals($expected, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
    }

    public function testOidcTokenHandlerConfigurationWithAllowedTimeDrift()
    {
        $container = new ContainerBuilder();
        $jwkset = '{"keys":[{"kty":"EC","crv":"P-256","x":"FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars","y":"rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY","d":"4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0"},{"kty":"EC","crv":"P-256","x":"0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4","y":"KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo","d":"iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220"}]}';
        $config = [
            'token_handler' => [
                'oidc' => [
                    'enforce_at_jwt_type' => false,
                    'algorithms' => ['RS256', 'ES256'],
                    'issuers' => ['https://www.example.com'],
                    'audience' => 'audience',
                    'keyset' => $jwkset,
                    'allowed_time_drift' => 5,
                ],
            ],
        ];

        $factory = new AccessTokenFactory($this->createTokenHandlerFactories());
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $expected = [
            'index_0' => (new ChildDefinition('security.access_token_handler.oidc.signature'))
                ->replaceArgument(0, ['RS256', 'ES256']),
            'index_1' => (new ChildDefinition('security.access_token_handler.oidc.jwkset'))
                ->replaceArgument(0, $jwkset),
            'index_2' => 'audience',
            'index_3' => ['https://www.example.com'],
            'index_4' => 'sub',
            'index_7' => 5,
            'index_8' => false,
        ];
        $this->assertEquals($expected, $container->getDefinition('security.access_token_handler.firewall1')->getArguments());
    }
}

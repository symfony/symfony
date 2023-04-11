<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FirewallListenerFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider\DummyProvider;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUserChecker;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\HttpBasicAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

class SecurityExtensionTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testInvalidCheckPath()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The check_path "/some_area/login_check" for login method "form_login" is not matched by the firewall pattern "/secured_area/.*".');
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/secured_area/.*',
                    'form_login' => [
                        'check_path' => '/some_area/login_check',
                    ],
                ],
            ],
        ]);

        $container->compile();
    }

    public function testFirewallWithInvalidUserProvider()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unable to create definition for "security.user.provider.concrete.my_foo" user provider');
        $container = $this->getRawContainer();

        $extension = $container->getExtension('security');
        $extension->addUserProviderFactory(new DummyProvider());

        $container->loadFromExtension('security', [
            'providers' => [
                'my_foo' => ['foo' => []],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
        ]);

        $container->compile();
    }

    public function testDisableRoleHierarchyVoter()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],

            'role_hierarchy' => null,

            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => null,
                ],
            ],
        ]);

        $container->compile();

        $this->assertFalse($container->hasDefinition('security.access.role_hierarchy_voter'));
    }

    public function testSwitchUserNotStatelessOnStatelessFirewall()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'stateless' => true,
                    'http_basic' => null,
                    'switch_user' => true,
                ],
            ],
        ]);

        $container->compile();

        $this->assertTrue($container->getDefinition('security.authentication.switchuser_listener.some_firewall')->getArgument(9));
    }

    public function testPerListenerProvider()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'foo'],
                'second' => ['id' => 'bar'],
            ],

            'firewalls' => [
                'default' => [
                    'http_basic' => ['provider' => 'second'],
                ],
            ],
        ]);

        $container->compile();
        $this->addToAssertionCount(1);
    }

    public function testMissingProviderForListener()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Not configuring explicitly the provider for the "http_basic" authenticator on "ambiguous" firewall is ambiguous as there is more than one registered provider.');
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'foo'],
                'second' => ['id' => 'bar'],
            ],

            'firewalls' => [
                'ambiguous' => [
                    'http_basic' => true,
                    'form_login' => ['provider' => 'second'],
                ],
            ],
        ]);

        $container->compile();
    }

    public function testPerListenerProviderWithRememberMeAndAnonymous()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'foo'],
                'second' => ['id' => 'bar'],
            ],

            'firewalls' => [
                'default' => [
                    'form_login' => ['provider' => 'second'],
                    'remember_me' => [],
                ],
            ],
        ]);

        $container->compile();
        $this->addToAssertionCount(1);
    }

    public function testRegisterRequestMatchersWithAllowIfExpression()
    {
        $container = $this->getRawContainer();

        $rawExpression = "'foo' == 'bar' or 1 in [1, 3, 3]";

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                ['path' => '/', 'allow_if' => $rawExpression],
            ],
        ]);

        $container->compile();
        $accessMap = $container->getDefinition('security.access_map');
        $this->assertCount(1, $accessMap->getMethodCalls());
        $call = $accessMap->getMethodCalls()[0];
        $this->assertSame('add', $call[0]);
        $args = $call[1];
        $this->assertCount(3, $args);
        $expressionId = $args[1][0];
        $this->assertTrue($container->hasDefinition($expressionId));
        $expressionDef = $container->getDefinition($expressionId);
        $this->assertSame(Expression::class, $expressionDef->getClass());
        $this->assertSame($rawExpression, $expressionDef->getArgument(0));

        $this->assertTrue($container->hasDefinition('security.cache_warmer.expression'));
        $this->assertEquals(
            new IteratorArgument([new Reference($expressionId)]),
            $container->getDefinition('security.cache_warmer.expression')->getArgument(0)
        );
    }

    public function testRegisterAccessControlWithSpecifiedRequestMatcherService()
    {
        $container = $this->getRawContainer();

        $requestMatcherId = 'My\Test\RequestMatcher';
        $requestMatcher = new PathRequestMatcher('/');
        $container->set($requestMatcherId, $requestMatcher);

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                ['request_matcher' => $requestMatcherId],
            ],
        ]);

        $container->compile();
        $accessMap = $container->getDefinition('security.access_map');
        $this->assertCount(1, $accessMap->getMethodCalls());
        $call = $accessMap->getMethodCalls()[0];
        $this->assertSame('add', $call[0]);
        $args = $call[1];
        $this->assertCount(3, $args);
        $this->assertSame($requestMatcherId, (string) $args[0]);
    }

    /** @dataProvider provideAdditionalRequestMatcherConstraints */
    public function testRegisterAccessControlWithRequestMatcherAndAdditionalOptionsThrowsInvalidException(array $additionalConstraints)
    {
        $container = $this->getRawContainer();

        $requestMatcherId = 'My\Test\RequestMatcher';
        $requestMatcher = new PathRequestMatcher('/');
        $container->set($requestMatcherId, $requestMatcher);

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                array_merge(['request_matcher' => $requestMatcherId], $additionalConstraints),
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "request_matcher" option should not be specified alongside other options. Consider integrating your constraints inside your RequestMatcher directly.');

        $container->compile();
    }

    public static function provideAdditionalRequestMatcherConstraints()
    {
        yield 'Invalid configuration with path' => [['path' => '^/url']];
        yield 'Invalid configuration with host' => [['host' => 'example.com']];
        yield 'Invalid configuration with port' => [['port' => 80]];
        yield 'Invalid configuration with methods' => [['methods' => ['POST']]];
        yield 'Invalid configuration with ips' => [['ips' => ['0.0.0.0']]];
        yield 'Invalid configuration with attributes' => [['attributes' => ['_route' => 'foo_route']]];
        yield 'Invalid configuration with route' => [['route' => 'foo_route']];
    }

    public function testRegisterAccessControlWithSpecifiedAttributes()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                ['attributes' => ['_route' => 'foo_route']],
            ],
        ]);

        $container->compile();

        $accessMap = $container->getDefinition('security.access_map');
        $this->assertCount(1, $accessMap->getMethodCalls());
        $call = $accessMap->getMethodCalls()[0];
        $this->assertSame('add', $call[0]);
        $args = $call[1];

        $chainRequestMatcherDefinition = $container->getDefinition((string) $args[0]);
        $chainRequestMatcherConstructorArguments = $chainRequestMatcherDefinition->getArguments();
        $attributesRequestMatcher = $container->getDefinition((string) $chainRequestMatcherConstructorArguments[0][0]);

        $this->assertCount(1, $attributesRequestMatcher->getArguments());
        $this->assertArrayHasKey('_route', $attributesRequestMatcher->getArgument(0));
        $this->assertSame('foo_route', $attributesRequestMatcher->getArgument(0)['_route']);
    }

    public function testRegisterAccessControlWithSpecifiedRoute()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                ['route' => 'foo_route'],
            ],
        ]);

        $container->compile();

        $accessMap = $container->getDefinition('security.access_map');
        $this->assertCount(1, $accessMap->getMethodCalls());
        $call = $accessMap->getMethodCalls()[0];
        $this->assertSame('add', $call[0]);
        $args = $call[1];

        $chainRequestMatcherDefinition = $container->getDefinition((string) $args[0]);
        $chainRequestMatcherConstructorArguments = $chainRequestMatcherDefinition->getArguments();
        $attributesRequestMatcher = $container->getDefinition((string) $chainRequestMatcherConstructorArguments[0][0]);

        $this->assertCount(1, $attributesRequestMatcher->getArguments());
        $this->assertArrayHasKey('_route', $attributesRequestMatcher->getArgument(0));
        $this->assertSame('foo_route', $attributesRequestMatcher->getArgument(0)['_route']);
    }

    public function testRegisterAccessControlWithSpecifiedAttributesThrowsException()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                ['route' => 'anything', 'attributes' => ['_route' => 'foo_route']],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "route" option should not be specified alongside "attributes._route" option. Use just one of the options.');

        $container->compile();
    }

    public function testRemovesExpressionCacheWarmerDefinitionIfNoExpressions()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
        ]);
        $container->compile();

        $this->assertFalse($container->hasDefinition('security.cache_warmer.expression'));
    }

    public function testRegisterTheUserProviderAlias()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => null,
                ],
            ],
        ]);

        $container->compile();

        $this->assertTrue($container->hasAlias(UserProviderInterface::class));
    }

    public function testDoNotRegisterTheUserProviderAliasWithMultipleProviders()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'foo'],
                'second' => ['id' => 'bar'],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => ['provider' => 'second'],
                ],
            ],
        ]);

        $container->compile();

        $this->assertFalse($container->has(UserProviderInterface::class));
    }

    /**
     * @group legacy
     */
    public function testFirewallWithNoUserProviderTriggerDeprecation()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'foo'],
                'second' => ['id' => 'foo'],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'custom_authenticator' => 'my_authenticator',
                ],
            ],
        ]);

        $this->expectDeprecation('Since symfony/security-bundle 5.4: Not configuring explicitly the provider for the "some_firewall" firewall is deprecated because it\'s ambiguous as there is more than one registered provider. Set the "provider" key to one of the configured providers, even if your custom authenticators don\'t use it.');

        $container->compile();
    }

    /**
     * @dataProvider acceptableIpsProvider
     */
    public function testAcceptableAccessControlIps($ips)
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                ['ips' => $ips, 'path' => '/somewhere', 'roles' => 'IS_AUTHENTICATED_FULLY'],
            ],
        ]);

        $container->compile();

        $this->assertTrue(true, 'Ip addresses is successfully consumed: '.(\is_string($ips) ? $ips : json_encode($ips)));
    }

    public function testCustomRememberMeHandler()
    {
        $container = $this->getRawContainer();

        $container->register('custom_remember_me', \stdClass::class);
        $container->loadFromExtension('security', [
            'firewalls' => [
                'default' => [
                    'remember_me' => ['service' => 'custom_remember_me'],
                ],
            ],
        ]);

        $container->compile();

        $handler = $container->getDefinition('security.authenticator.remember_me_handler.default');
        $this->assertEquals(\stdClass::class, $handler->getClass());
        $this->assertEquals([['firewall' => 'default']], $handler->getTag('security.remember_me_handler'));
    }

    public function testSecretRememberMeHasher()
    {
        $container = $this->getRawContainer();

        $container->register('custom_remember_me', \stdClass::class);
        $container->loadFromExtension('security', [
            'firewalls' => [
                'default' => [
                    'remember_me' => ['secret' => 'very'],
                ],
            ],
        ]);

        $container->compile();

        $handler = $container->getDefinition('security.authenticator.remember_me_signature_hasher.default');
        $this->assertSame('very', $handler->getArgument(2));
    }

    public function sessionConfigurationProvider()
    {
        return [
            [
                false,
                null,
                false,
            ],
            [
                [
                    'storage_factory_id' => 'session.storage.factory.native',
                    'cookie_secure' => true,
                    'cookie_samesite' => 'lax',
                    'save_path' => null,
                ],
                'lax',
                true,
            ],
        ];
    }

    public static function acceptableIpsProvider(): iterable
    {
        yield [['127.0.0.1']];
        yield ['127.0.0.1'];
        yield ['127.0.0.1, 127.0.0.2'];
        yield ['127.0.0.1/8, 127.0.0.2/16'];
        yield [['127.0.0.1/8, 127.0.0.2/16']];
        yield [['127.0.0.1/8', '127.0.0.2/16']];
    }

    public function testSwitchUserWithSeveralDefinedProvidersButNoFirewallRootProviderConfigured()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'foo'],
                'second' => ['id' => 'bar'],
            ],

            'firewalls' => [
                'foobar' => [
                    'switch_user' => [
                        'provider' => 'second',
                    ],
                ],
            ],
        ]);

        $container->compile();

        $this->assertEquals(new Reference('security.user.provider.concrete.second'), $container->getDefinition('security.authentication.switchuser_listener.foobar')->getArgument(1));
    }

    public function testInvalidAccessControlWithEmptyRow()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                [],
                ['path' => '/admin', 'roles' => 'ROLE_ADMIN'],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('One or more access control items are empty. Did you accidentally add lines only containing a "-" under "security.access_control"?');
        $container->compile();
    }

    public function testValidAccessControlWithEmptyRow()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],
            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                ['path' => '^/login'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ]);

        $container->compile();

        $this->assertTrue(true, 'extension throws an InvalidConfigurationException if there is one more more empty access control items');
    }

    /**
     * @dataProvider provideEntryPointRequiredData
     */
    public function testEntryPointRequired(array $firewall, $messageRegex)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches($messageRegex);

        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'users'],
            ],

            'firewalls' => [
                'main' => $firewall,
            ],
        ]);

        $container->compile();
    }

    public static function provideEntryPointRequiredData()
    {
        // more than one entry point available and not explicitly set
        yield [
            ['http_basic' => true, 'form_login' => true],
            '/Because you have multiple authenticators in firewall "main", you need to set the "entry_point" key to one of your authenticators \("form_login", "http_basic"\) or a service ID implementing/',
        ];
    }

    /**
     * @dataProvider provideConfigureCustomAuthenticatorData
     */
    public function testConfigureCustomAuthenticator(array $firewall, array $expectedAuthenticators)
    {
        $container = $this->getRawContainer();
        $container->register(TestAuthenticator::class);
        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'users'],
            ],

            'firewalls' => [
                'main' => $firewall,
            ],
        ]);

        $container->compile();

        $this->assertEquals($expectedAuthenticators, array_map('strval', $container->getDefinition('security.authenticator.manager.main')->getArgument(0)));
    }

    public static function provideConfigureCustomAuthenticatorData()
    {
        yield [
            ['custom_authenticator' => TestAuthenticator::class],
            [TestAuthenticator::class],
        ];

        yield [
            ['custom_authenticators' => [TestAuthenticator::class, HttpBasicAuthenticator::class]],
            [TestAuthenticator::class, HttpBasicAuthenticator::class],
        ];
    }

    public function testCompilesWithoutSessionListenerWithStatelessFirewallWithAuthenticatorManager()
    {
        $container = $this->getRawContainer();

        $firewallId = 'stateless_firewall';
        $container->loadFromExtension('security', [
            'firewalls' => [
                $firewallId => [
                    'pattern' => '/.*',
                    'stateless' => true,
                    'http_basic' => null,
                ],
            ],
        ]);

        $container->compile();

        $this->assertFalse($container->has('security.listener.session.'.$firewallId));
    }

    public function testCompilesWithSessionListenerWithStatefulllFirewallWithAuthenticatorManager()
    {
        $container = $this->getRawContainer();

        $firewallId = 'statefull_firewall';
        $container->loadFromExtension('security', [
            'firewalls' => [
                $firewallId => [
                    'pattern' => '/.*',
                    'stateless' => false,
                    'http_basic' => null,
                ],
            ],
        ]);

        $container->compile();

        $this->assertTrue($container->has('security.listener.session.'.$firewallId));
    }

    /**
     * @dataProvider provideUserCheckerConfig
     */
    public function testUserCheckerWithAuthenticatorManager(array $config, string $expectedUserCheckerClass)
    {
        $container = $this->getRawContainer();
        $container->register(TestUserChecker::class);

        $container->loadFromExtension('security', [
            'firewalls' => [
                'main' => array_merge([
                    'pattern' => '/.*',
                    'http_basic' => true,
                ], $config),
            ],
        ]);

        $container->compile();

        $userCheckerId = (string) $container->getDefinition('security.listener.user_checker.main')->getArgument(0);
        $this->assertTrue($container->has($userCheckerId));
        $this->assertEquals($expectedUserCheckerClass, $container->findDefinition($userCheckerId)->getClass());
    }

    public static function provideUserCheckerConfig()
    {
        yield [[], InMemoryUserChecker::class];
        yield [['user_checker' => TestUserChecker::class], TestUserChecker::class];
    }

    public function testConfigureCustomFirewallListener()
    {
        $container = $this->getRawContainer();
        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addAuthenticatorFactory(new TestFirewallListenerFactory());

        $container->loadFromExtension('security', [
            'firewalls' => [
                'main' => [
                    'custom_listener' => true,
                ],
            ],
        ]);

        $container->compile();

        /** @var IteratorArgument $listenersIteratorArgument */
        $listenersIteratorArgument = $container->getDefinition('security.firewall.map.context.main')->getArgument(0);
        $firewallListeners = array_map('strval', $listenersIteratorArgument->getValues());
        $this->assertContains('custom_firewall_listener_id', $firewallListeners);
    }

    public function testClearSiteDataLogoutListenerEnabled()
    {
        $container = $this->getRawContainer();

        $firewallId = 'logout_firewall';
        $container->loadFromExtension('security', [
            'firewalls' => [
                $firewallId => [
                    'logout' => [
                        'clear_site_data' => ['*'],
                    ],
                ],
            ],
        ]);

        $container->compile();

        $this->assertTrue($container->has('security.logout.listener.clear_site_data.'.$firewallId));
        $listenerArgument = $container->getDefinition('security.logout.listener.clear_site_data.'.$firewallId)->getArgument(0);
        $this->assertSame(['*'], $listenerArgument);
    }

    public function testClearSiteDataLogoutListenerDisabled()
    {
        $container = $this->getRawContainer();

        $firewallId = 'logout_firewall';
        $container->loadFromExtension('security', [
            'firewalls' => [
                $firewallId => [
                    'logout' => [
                        'clear_site_data' => [],
                    ],
                ],
            ],
        ]);

        $container->compile();

        $this->assertFalse($container->has('security.logout.listener.clear_site_data.'.$firewallId));
    }

    /**
     * @group legacy
     */
    public function testNothingDoneWithEmptyConfiguration()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security');

        $this->expectDeprecation('Since symfony/security-bundle 6.3: Enabling bundle "Symfony\Bundle\SecurityBundle\SecurityBundle" and not configuring it is deprecated.');

        $container->compile();

        $this->assertFalse($container->has('security.authorization_checker'));
    }

    protected function getRawContainer()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $security = new SecurityExtension();
        $container->registerExtension($security);

        $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        $bundle = new SecurityBundle();
        $bundle->build($container);

        return $container;
    }

    protected function getContainer()
    {
        $container = $this->getRawContainer();
        $container->compile();

        return $container;
    }
}

class TestAuthenticator implements AuthenticatorInterface
{
    public function supports(Request $request): ?bool
    {
    }

    public function authenticate(Request $request): Passport
    {
    }

    /**
     * @internal for compatibility with Symfony 5.4
     */
    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
    }
}

class TestUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}

class TestFirewallListenerFactory implements AuthenticatorFactoryInterface, FirewallListenerFactoryInterface
{
    public function createListeners(ContainerBuilder $container, string $firewallName, array $config): array
    {
        $container->register('custom_firewall_listener_id', \stdClass::class);

        return ['custom_firewall_listener_id'];
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        return 'test_authenticator_id';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getKey(): string
    {
        return 'custom_listener';
    }

    public function addConfiguration(NodeDefinition $builder): void
    {
    }
}

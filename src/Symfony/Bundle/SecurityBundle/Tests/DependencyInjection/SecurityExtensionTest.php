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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FirewallListenerFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider\DummyProvider;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallEntryPointBundle\Security\EntryPointStub;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\GuardedBundle\AppCustomAuthenticator;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
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
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('The check_path "/some_area/login_check" for login method "form_login" is not matched by the firewall pattern "/secured_area/.*".');
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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

    /**
     * @group legacy
     */
    public function testFirewallWithoutAuthenticationListener()
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('No authentication listener registered for firewall "some_firewall"');
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                ],
            ],
        ]);

        $container->compile();
    }

    public function testFirewallWithInvalidUserProvider()
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('Unable to create definition for "security.user.provider.concrete.my_foo" user provider');
        $container = $this->getRawContainer();

        $extension = $container->getExtension('security');
        $extension->addUserProviderFactory(new DummyProvider());

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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
            'enable_authenticator_manager' => true,
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

        self::assertFalse($container->hasDefinition('security.access.role_hierarchy_voter'));
    }

    /**
     * @group legacy
     */
    public function testGuardHandlerIsPassedStatelessFirewalls()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '^/admin',
                    'http_basic' => null,
                ],
                'stateless_firewall' => [
                    'pattern' => '/.*',
                    'stateless' => true,
                    'http_basic' => null,
                ],
            ],
        ]);

        $container->compile();
        $definition = $container->getDefinition('security.authentication.guard_handler');
        self::assertSame(['stateless_firewall'], $definition->getArgument(2));
    }

    public function testSwitchUserNotStatelessOnStatelessFirewall()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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

        self::assertTrue($container->getDefinition('security.authentication.switchuser_listener.some_firewall')->getArgument(9));
    }

    public function testPerListenerProvider()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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
        self::addToAssertionCount(1);
    }

    public function testMissingProviderForListener()
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('Not configuring explicitly the provider for the "http_basic" authenticator on "ambiguous" firewall is ambiguous as there is more than one registered provider.');
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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
            'enable_authenticator_manager' => true,
            'providers' => [
                'first' => ['id' => 'foo'],
                'second' => ['id' => 'bar'],
            ],

            'firewalls' => [
                'default' => [
                    'form_login' => ['provider' => 'second'],
                    'remember_me' => ['secret' => 'baz'],
                ],
            ],
        ]);

        $container->compile();
        self::addToAssertionCount(1);
    }

    public function testRegisterRequestMatchersWithAllowIfExpression()
    {
        $container = $this->getRawContainer();

        $rawExpression = "'foo' == 'bar' or 1 in [1, 3, 3]";

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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
        self::assertCount(1, $accessMap->getMethodCalls());
        $call = $accessMap->getMethodCalls()[0];
        self::assertSame('add', $call[0]);
        $args = $call[1];
        self::assertCount(3, $args);
        $expressionId = $args[1][0];
        self::assertTrue($container->hasDefinition($expressionId));
        $expressionDef = $container->getDefinition($expressionId);
        self::assertSame(Expression::class, $expressionDef->getClass());
        self::assertSame($rawExpression, $expressionDef->getArgument(0));

        self::assertTrue($container->hasDefinition('security.cache_warmer.expression'));
        self::assertEquals(new IteratorArgument([new Reference($expressionId)]), $container->getDefinition('security.cache_warmer.expression')->getArgument(0));
    }

    public function testRemovesExpressionCacheWarmerDefinitionIfNoExpressions()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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

        self::assertFalse($container->hasDefinition('security.cache_warmer.expression'));
    }

    public function testRegisterTheUserProviderAlias()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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

        self::assertTrue($container->hasAlias(UserProviderInterface::class));
    }

    public function testDoNotRegisterTheUserProviderAliasWithMultipleProviders()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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

        self::assertFalse($container->has(UserProviderInterface::class));
    }

    /**
     * @group legacy
     */
    public function testFirewallWithNoUserProviderTriggerDeprecation()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,

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
     * @dataProvider sessionConfigurationProvider
     * @group legacy
     */
    public function testRememberMeCookieInheritFrameworkSessionCookie($config, $samesite, $secure)
    {
        $container = $this->getRawContainer();

        $container->registerExtension(new FrameworkExtension());
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->setParameter('kernel.cache_dir', __DIR__);
        $container->setParameter('kernel.container_class', 'FooContainer');

        $container->loadFromExtension('security', [
            'firewalls' => [
                'default' => [
                    'form_login' => null,
                    'remember_me' => [],
                ],
            ],
        ]);
        $container->loadFromExtension('framework', [
            'session' => $config,
        ]);

        $container->compile();

        $definition = $container->getDefinition('security.authentication.rememberme.services.simplehash.default');

        self::assertEquals($samesite, $definition->getArgument(3)['samesite']);
        self::assertEquals($secure, $definition->getArgument(3)['secure']);
        self::assertSame('%kernel.secret%', $definition->getArgument(1));
    }

    /**
     * @dataProvider acceptableIpsProvider
     */
    public function testAcceptableAccessControlIps($ips)
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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

        self::assertTrue(true, 'Ip addresses is successfully consumed: '.(\is_string($ips) ? $ips : json_encode($ips)));
    }

    public function testCustomRememberMeHandler()
    {
        $container = $this->getRawContainer();

        $container->register('custom_remember_me', \stdClass::class);
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'firewalls' => [
                'default' => [
                    'remember_me' => ['secret' => 'very', 'service' => 'custom_remember_me'],
                ],
            ],
        ]);

        $container->compile();

        $handler = $container->getDefinition('security.authenticator.remember_me_handler.default');
        self::assertEquals(\stdClass::class, $handler->getClass());
        self::assertEquals([['firewall' => 'default']], $handler->getTag('security.remember_me_handler'));
    }

    public function testSecretRememberMeHasher()
    {
        $container = $this->getRawContainer();

        $container->register('custom_remember_me', \stdClass::class);
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'firewalls' => [
                'default' => [
                    'remember_me' => ['secret' => 'very'],
                ],
            ],
        ]);

        $container->compile();

        $handler = $container->getDefinition('security.authenticator.remember_me_signature_hasher.default');
        self::assertSame('very', $handler->getArgument(2));
    }

    public function testSecretRememberMeHandler()
    {
        $container = $this->getRawContainer();

        $container->register('custom_remember_me', \stdClass::class);
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'firewalls' => [
                'default' => [
                    'remember_me' => ['secret' => 'very', 'token_provider' => 'token_provider_id'],
                ],
            ],
        ]);

        $container->compile();

        $handler = $container->getDefinition('security.authenticator.remember_me_handler.default');
        self::assertSame('very', $handler->getArgument(1));
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

    public function acceptableIpsProvider(): iterable
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
            'enable_authenticator_manager' => true,
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

        self::assertEquals(new Reference('security.user.provider.concrete.second'), $container->getDefinition('security.authentication.switchuser_listener.foobar')->getArgument(1));
    }

    public function testInvalidAccessControlWithEmptyRow()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('One or more access control items are empty. Did you accidentally add lines only containing a "-" under "security.access_control"?');
        $container->compile();
    }

    public function testValidAccessControlWithEmptyRow()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
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

        self::assertTrue(true, 'extension throws an InvalidConfigurationException if there is one more more empty access control items');
    }

    /**
     * @group legacy
     * @dataProvider provideEntryPointFirewalls
     */
    public function testAuthenticatorManagerEnabledEntryPoint(array $firewall, $entryPointId)
    {
        $container = $this->getRawContainer();
        $container->register(AppCustomAuthenticator::class);
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'providers' => [
                'first' => ['id' => 'users'],
            ],

            'firewalls' => [
                'main' => $firewall,
            ],
        ]);

        $container->compile();

        self::assertEquals($entryPointId, (string) $container->getDefinition('security.firewall.map.config.main')->getArgument(7));
        self::assertEquals($entryPointId, (string) $container->getDefinition('security.exception_listener.main')->getArgument(4));
    }

    public function provideEntryPointFirewalls()
    {
        // only one entry point available
        yield [['http_basic' => true], 'security.authenticator.http_basic.main'];
        // explicitly configured by authenticator key
        yield [['form_login' => true, 'http_basic' => true, 'entry_point' => 'form_login'], 'security.authenticator.form_login.main'];
        // explicitly configured another service
        yield [['form_login' => true, 'entry_point' => EntryPointStub::class], EntryPointStub::class];
        // no entry point required
        yield [['json_login' => true], null];

        // only one guard authenticator entry point available
        yield [[
            'guard' => ['authenticators' => [AppCustomAuthenticator::class]],
        ], 'security.authenticator.guard.main.0'];
    }

    /**
     * @dataProvider provideEntryPointRequiredData
     */
    public function testEntryPointRequired(array $firewall, $messageRegex)
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessageMatches($messageRegex);

        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'providers' => [
                'first' => ['id' => 'users'],
            ],

            'firewalls' => [
                'main' => $firewall,
            ],
        ]);

        $container->compile();
    }

    public function provideEntryPointRequiredData()
    {
        // more than one entry point available and not explicitly set
        yield [
            ['http_basic' => true, 'form_login' => true],
            '/Because you have multiple authenticators in firewall "main", you need to set the "entry_point" key to one of your authenticators \("form_login", "http_basic"\) or a service ID implementing/',
        ];
    }

    /**
     * @group legacy
     */
    public function testAlwaysAuthenticateBeforeGrantingCannotBeTrueWithAuthenticatorManager()
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('The security option "always_authenticate_before_granting" cannot be used when "enable_authenticator_manager" is set to true. If you rely on this behavior, set it to false.');

        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'always_authenticate_before_granting' => true,
            'firewalls' => ['main' => []],
        ]);

        $container->compile();
    }

    /**
     * @dataProvider provideConfigureCustomAuthenticatorData
     */
    public function testConfigureCustomAuthenticator(array $firewall, array $expectedAuthenticators)
    {
        $container = $this->getRawContainer();
        $container->register(TestAuthenticator::class);
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'providers' => [
                'first' => ['id' => 'users'],
            ],

            'firewalls' => [
                'main' => $firewall,
            ],
        ]);

        $container->compile();

        self::assertEquals($expectedAuthenticators, array_map('strval', $container->getDefinition('security.authenticator.manager.main')->getArgument(0)));
    }

    public function provideConfigureCustomAuthenticatorData()
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
            'enable_authenticator_manager' => true,
            'firewalls' => [
                $firewallId => [
                    'pattern' => '/.*',
                    'stateless' => true,
                    'http_basic' => null,
                ],
            ],
        ]);

        $container->compile();

        self::assertFalse($container->has('security.listener.session.'.$firewallId));
    }

    public function testCompilesWithSessionListenerWithStatefulllFirewallWithAuthenticatorManager()
    {
        $container = $this->getRawContainer();

        $firewallId = 'statefull_firewall';
        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'firewalls' => [
                $firewallId => [
                    'pattern' => '/.*',
                    'stateless' => false,
                    'http_basic' => null,
                ],
            ],
        ]);

        $container->compile();

        self::assertTrue($container->has('security.listener.session.'.$firewallId));
    }

    /**
     * @dataProvider provideUserCheckerConfig
     */
    public function testUserCheckerWithAuthenticatorManager(array $config, string $expectedUserCheckerClass)
    {
        $container = $this->getRawContainer();
        $container->register(TestUserChecker::class);

        $container->loadFromExtension('security', [
            'enable_authenticator_manager' => true,
            'firewalls' => [
                'main' => array_merge([
                    'pattern' => '/.*',
                    'http_basic' => true,
                ], $config),
            ],
        ]);

        $container->compile();

        $userCheckerId = (string) $container->getDefinition('security.listener.user_checker.main')->getArgument(0);
        self::assertTrue($container->has($userCheckerId));
        self::assertEquals($expectedUserCheckerClass, $container->findDefinition($userCheckerId)->getClass());
    }

    public function provideUserCheckerConfig()
    {
        yield [[], InMemoryUserChecker::class];
        yield [['user_checker' => TestUserChecker::class], TestUserChecker::class];
    }

    /**
     * @group legacy
     */
    public function testConfigureCustomFirewallListener()
    {
        $container = $this->getRawContainer();
        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new TestFirewallListenerFactory());

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
        self::assertContains('custom_firewall_listener_id', $firewallListeners);
    }

    /**
     * @group legacy
     */
    public function testLegacyAuthorizationManagerSignature()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', [
            'always_authenticate_before_granting' => true,
            'firewalls' => ['main' => ['http_basic' => true]],
        ]);

        $container->compile();

        $args = $container->getDefinition('security.authorization_checker')->getArguments();
        self::assertEquals('security.token_storage', (string) $args[0]);
        self::assertEquals('security.authentication.manager', (string) $args[1]);
        self::assertEquals('security.access.decision_manager', (string) $args[2]);
        self::assertEquals('%security.access.always_authenticate_before_granting%', (string) $args[3]);
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

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
    }
}

class TestUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user)
    {
    }

    public function checkPostAuth(UserInterface $user)
    {
    }
}

class TestFirewallListenerFactory implements SecurityFactoryInterface, FirewallListenerFactoryInterface
{
    public function createListeners(ContainerBuilder $container, string $firewallName, array $config): array
    {
        $container->register('custom_firewall_listener_id', \stdClass::class);

        return ['custom_firewall_listener_id'];
    }

    public function create(ContainerBuilder $container, string $id, array $config, string $userProvider, ?string $defaultEntryPoint): array
    {
        $container->register('provider_id', \stdClass::class);
        $container->register('listener_id', \stdClass::class);

        return ['provider_id', 'listener_id', $defaultEntryPoint];
    }

    public function getPosition(): string
    {
        return 'form';
    }

    public function getKey(): string
    {
        return 'custom_listener';
    }

    public function addConfiguration(NodeDefinition $builder)
    {
    }
}

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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider\DummyProvider;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SecurityExtensionTest extends TestCase
{
    public function testInvalidCheckPath()
    {
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
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

    public function testFirewallWithoutAuthenticationListener()
    {
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
        $this->expectExceptionMessage('No authentication listener registered for firewall "some_firewall"');
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
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
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
        $this->assertSame(['stateless_firewall'], $definition->getArgument(2));
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
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
        $this->expectExceptionMessage('Not configuring explicitly the provider for the "http_basic" listener on "ambiguous" firewall is ambiguous as there is more than one registered provider.');
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

    public function testPerListenerProviderWithRememberMe()
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
                    'remember_me' => ['secret' => 'baz'],
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
     * @dataProvider sessionConfigurationProvider
     */
    public function testRememberMeCookieInheritFrameworkSessionCookie($config, $samesite, $secure)
    {
        $container = $this->getRawContainer();

        $container->registerExtension(new FrameworkExtension());
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->setParameter('kernel.cache_dir', __DIR__);

        $container->loadFromExtension('security', [
            'firewalls' => [
                'default' => [
                    'form_login' => null,
                    'remember_me' => ['secret' => 'baz'],
                ],
            ],
        ]);
        $container->loadFromExtension('framework', [
            'session' => $config,
        ]);

        $container->compile();

        $definition = $container->getDefinition('security.authentication.rememberme.services.simplehash.default');

        $this->assertEquals($samesite, $definition->getArgument(3)['samesite']);
        $this->assertEquals($secure, $definition->getArgument(3)['secure']);
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
                    'cookie_secure' => true,
                    'cookie_samesite' => 'lax',
                    'save_path' => null,
                ],
                'lax',
                true,
            ],
        ];
    }

    protected function getRawContainer()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $security = new SecurityExtension();
        $container->registerExtension($security);

        $bundle = new SecurityBundle();
        $bundle->build($container);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        return $container;
    }

    protected function getContainer()
    {
        $container = $this->getRawContainer();
        $container->compile();

        return $container;
    }
}

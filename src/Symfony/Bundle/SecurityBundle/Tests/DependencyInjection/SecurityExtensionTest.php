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
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider\DummyProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SecurityExtensionTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The check_path "/some_area/login_check" for login method "form_login" is not matched by the firewall pattern "/secured_area/.*".
     */
    public function testInvalidCheckPath()
    {
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
                    'logout_on_user_change' => true,
                ],
            ],
        ]);

        $container->compile();
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage No authentication listener registered for firewall "some_firewall"
     */
    public function testFirewallWithoutAuthenticationListener()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'default' => ['id' => 'foo'],
            ],

            'firewalls' => [
                'some_firewall' => [
                    'pattern' => '/.*',
                    'logout_on_user_change' => true,
                ],
            ],
        ]);

        $container->compile();
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Unable to create definition for "security.user.provider.concrete.my_foo" user provider
     */
    public function testFirewallWithInvalidUserProvider()
    {
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
                    'logout_on_user_change' => true,
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
                    'logout_on_user_change' => true,
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
                    'logout_on_user_change' => true,
                ],
                'stateless_firewall' => [
                    'pattern' => '/.*',
                    'stateless' => true,
                    'http_basic' => null,
                    'logout_on_user_change' => true,
                ],
            ],
        ]);

        $container->compile();
        $definition = $container->getDefinition('security.authentication.guard_handler');
        $this->assertSame(['stateless_firewall'], $definition->getArgument(2));
    }

    /**
     * @group legacy
     * @expectedDeprecation Not setting "logout_on_user_change" to true on firewall "some_firewall" is deprecated as of 3.4, it will always be true in 4.0.
     */
    public function testConfiguresLogoutOnUserChangeForContextListenersCorrectly()
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
                    'logout_on_user_change' => false,
                ],
                'some_other_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => null,
                    'logout_on_user_change' => true,
                ],
            ],
        ]);

        $container->compile();

        $this->assertEquals([['setLogoutOnUserChange', [false]]], $container->getDefinition('security.context_listener.0')->getMethodCalls());
        $this->assertEquals([['setLogoutOnUserChange', [true]]], $container->getDefinition('security.context_listener.1')->getMethodCalls());
    }

    /**
     * @group legacy
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Firewalls "some_firewall" and "some_other_firewall" need to have the same value for option "logout_on_user_change" as they are sharing the context "my_context"
     */
    public function testThrowsIfLogoutOnUserChangeDifferentForSharedContext()
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
                    'context' => 'my_context',
                    'logout_on_user_change' => false,
                ],
                'some_other_firewall' => [
                    'pattern' => '/.*',
                    'http_basic' => null,
                    'context' => 'my_context',
                    'logout_on_user_change' => true,
                ],
            ],
        ]);

        $container->compile();
    }

    /**
     * @group legacy
     * @expectedDeprecation Firewall "some_firewall" is configured as "stateless" but the "switch_user.stateless" key is set to false. Both should have the same value, the firewall's "stateless" value will be used as default value for the "switch_user.stateless" key in 4.0.
     */
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
                    'switch_user' => ['stateless' => false],
                    'logout_on_user_change' => true,
                ],
            ],
        ]);

        $container->compile();
    }

    /**
     * @group legacy
     * @expectedDeprecation Listener "http_basic" on firewall "default" has no "provider" set but multiple providers exist. Using the first configured provider (first) is deprecated since Symfony 3.4 and will throw an exception in 4.0, set the "provider" key on the firewall instead.
     */
    public function testDeprecationForAmbiguousProvider()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', [
            'providers' => [
                'first' => ['id' => 'foo'],
                'second' => ['id' => 'bar'],
            ],

            'firewalls' => [
                'default' => [
                    'http_basic' => null,
                    'logout_on_user_change' => true,
                ],
            ],
        ]);

        $container->compile();
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
                    'logout_on_user_change' => true,
                ],
            ],
        ]);

        $container->compile();
        $this->addToAssertionCount(1);
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
                    'logout_on_user_change' => true,
                    'remember_me' => ['secret' => 'baz'],
                ],
            ],
        ]);

        $container->compile();
        $this->addToAssertionCount(1);
    }

    protected function getRawContainer()
    {
        $container = new ContainerBuilder();
        $security = new SecurityExtension();
        $container->registerExtension($security);

        $bundle = new SecurityBundle();
        $bundle->build($container);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);

        return $container;
    }

    protected function getContainer()
    {
        $container = $this->getRawContainer();
        $container->compile();

        return $container;
    }
}

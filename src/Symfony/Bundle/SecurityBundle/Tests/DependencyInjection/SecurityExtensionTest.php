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
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SecurityExtensionTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The check_path "/some_area/login_check" for login method "form_login" is not matched by the firewall pattern "/secured_area/.*".
     */
    public function testInvalidCheckPath()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/secured_area/.*',
                    'form_login' => array(
                        'check_path' => '/some_area/login_check',
                    ),
                ),
            ),
        ));

        $container->compile();
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage No authentication listener registered for firewall "some_firewall"
     */
    public function testFirewallWithoutAuthenticationListener()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                ),
            ),
        ));

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

        $container->loadFromExtension('security', array(
            'providers' => array(
                'my_foo' => array('foo' => array()),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                    'http_basic' => array(),
                ),
            ),
        ));

        $container->compile();
    }

    public function testDisableRoleHierarchyVoter()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'role_hierarchy' => null,

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                    'http_basic' => null,
                ),
            ),
        ));

        $container->compile();

        $this->assertFalse($container->hasDefinition('security.access.role_hierarchy_voter'));
    }

    public function testSwitchUserNotStatelessOnStatelessFirewall()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'stateless' => true,
                    'http_basic' => null,
                    'switch_user' => true,
                ),
            ),
        ));

        $container->compile();

        $this->assertTrue($container->getDefinition('security.authentication.switchuser_listener.some_firewall')->getArgument(9));
    }

    public function testPerListenerProvider()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', array(
            'providers' => array(
                'first' => array('id' => 'foo'),
                'second' => array('id' => 'bar'),
            ),

            'firewalls' => array(
                'default' => array(
                    'http_basic' => array('provider' => 'second'),
                ),
            ),
        ));

        $container->compile();
        $this->addToAssertionCount(1);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Not configuring explicitly the provider for the "http_basic" listener on "ambiguous" firewall is ambiguous as there is more than one registered provider.
     */
    public function testMissingProviderForListener()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', array(
            'providers' => array(
                'first' => array('id' => 'foo'),
                'second' => array('id' => 'bar'),
            ),

            'firewalls' => array(
                'ambiguous' => array(
                    'http_basic' => true,
                    'form_login' => array('provider' => 'second'),
                ),
            ),
        ));

        $container->compile();
    }

    public function testPerListenerProviderWithRememberMe()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', array(
            'providers' => array(
                'first' => array('id' => 'foo'),
                'second' => array('id' => 'bar'),
            ),

            'firewalls' => array(
                'default' => array(
                    'form_login' => array('provider' => 'second'),
                    'remember_me' => array('secret' => 'baz'),
                ),
            ),
        ));

        $container->compile();
        $this->addToAssertionCount(1);
    }

    public function testRegisterRequestMatchersWithAllowIfExpression()
    {
        $container = $this->getRawContainer();

        $rawExpression = "'foo' == 'bar' or 1 in [1, 3, 3]";

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),
            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                    'http_basic' => array(),
                ),
            ),
            'access_control' => array(
                array('path' => '/', 'allow_if' => $rawExpression),
            ),
        ));

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
            new IteratorArgument(array(new Reference($expressionId))),
            $container->getDefinition('security.cache_warmer.expression')->getArgument(0)
        );
    }

    public function testRemovesExpressionCacheWarmerDefinitionIfNoExpressions()
    {
        $container = $this->getRawContainer();
        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),
            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                    'http_basic' => array(),
                ),
            ),
        ));
        $container->compile();

        $this->assertFalse($container->hasDefinition('security.cache_warmer.expression'));
    }

    public function testRegisterTheUserProviderAlias()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                    'http_basic' => null,
                ),
            ),
        ));

        $container->compile();

        $this->assertTrue($container->hasAlias(UserProviderInterface::class));
    }

    public function testDoNotRegisterTheUserProviderAliasWithMultipleProviders()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'first' => array('id' => 'foo'),
                'second' => array('id' => 'bar'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                    'http_basic' => array('provider' => 'second'),
                ),
            ),
        ));

        $container->compile();

        $this->assertFalse($container->has(UserProviderInterface::class));
    }

    protected function getRawContainer()
    {
        $container = new ContainerBuilder();
        $security = new SecurityExtension();
        $container->registerExtension($security);

        $bundle = new SecurityBundle();
        $bundle->build($container);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());

        return $container;
    }

    protected function getContainer()
    {
        $container = $this->getRawContainer();
        $container->compile();

        return $container;
    }
}

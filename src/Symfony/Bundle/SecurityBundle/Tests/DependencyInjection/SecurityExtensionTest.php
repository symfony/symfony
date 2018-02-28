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

    public function testRegisterRequestMatchersWithAllowIfExpression()
    {
        $container = $this->getRawContainer();

        $rawExpression = "'foo' == 'bar' or 1 in [1, 3, 3]";
        $rawExpressionWithCustomFunction = "foo('bar')";

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
                array('path' => '/foo', 'allow_if' => $rawExpressionWithCustomFunction),
            ),
        ));

        $container->compile();

        $accessMap = $container->getDefinition('security.access_map');
        $this->assertCount(2, $accessMap->getMethodCalls());

        // first expression without any custom functions can be parsed already
        $call = $accessMap->getMethodCalls()[0];
        $this->assertSame('add', $call[0]);
        $args = $call[1];
        $this->assertCount(3, $args);
        $expressionId = $args[1][0];
        $this->assertTrue($container->hasDefinition($expressionId));
        $expressionDef = $container->getDefinition($expressionId);
        $this->assertSame('Symfony\Component\ExpressionLanguage\SerializedParsedExpression', $expressionDef->getClass());
        $this->assertSame($rawExpression, $expressionDef->getArgument(0));
        $this->assertInstanceOf('Symfony\Component\ExpressionLanguage\Node\BinaryNode', unserialize($expressionDef->getArgument(1)));

        // second expression cannot be parsed at compile-time so we use an Expression instead and let it be parsed at run-time
        $call = $accessMap->getMethodCalls()[1];
        $this->assertSame('add', $call[0]);
        $args = $call[1];
        $this->assertCount(3, $args);
        $expressionId = $args[1][0];
        $this->assertTrue($container->hasDefinition($expressionId));
        $expressionDef = $container->getDefinition($expressionId);
        $this->assertSame('Symfony\Component\ExpressionLanguage\Expression', $expressionDef->getClass());
        $this->assertSame($rawExpressionWithCustomFunction, $expressionDef->getArgument(0));
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

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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class CompleteConfigurationTest extends \PHPUnit_Framework_TestCase
{
    private static $containerCache = array();

    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testRolesHierarchy()
    {
        $container = $this->getContainer('container1');
        $this->assertEquals(array(
            'ROLE_ADMIN' => array('ROLE_USER'),
            'ROLE_SUPER_ADMIN' => array('ROLE_USER', 'ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'),
            'ROLE_REMOTE' => array('ROLE_USER', 'ROLE_ADMIN'),
        ), $container->getParameter('security.role_hierarchy.roles'));
    }

    public function testUserProviders()
    {
        $container = $this->getContainer('container1');

        $providers = array_values(array_filter($container->getServiceIds(), function ($key) { return 0 === strpos($key, 'security.user.provider.concrete'); }));

        $expectedProviders = array(
            'security.user.provider.concrete.default',
            'security.user.provider.concrete.default_foo',
            'security.user.provider.concrete.digest',
            'security.user.provider.concrete.digest_foo',
            'security.user.provider.concrete.basic',
            'security.user.provider.concrete.basic_foo',
            'security.user.provider.concrete.basic_bar',
            'security.user.provider.concrete.service',
            'security.user.provider.concrete.chain',
        );

        $this->assertEquals(array(), array_diff($expectedProviders, $providers));
        $this->assertEquals(array(), array_diff($providers, $expectedProviders));

        // chain provider
        $this->assertEquals(array(array(
            new Reference('security.user.provider.concrete.service'),
            new Reference('security.user.provider.concrete.basic'),
        )), $container->getDefinition('security.user.provider.concrete.chain')->getArguments());
    }

    public function testFirewalls()
    {
        $container = $this->getContainer('container1');

        $arguments = $container->getDefinition('security.firewall.map')->getArguments();
        $listeners = array();
        foreach (array_keys($arguments[1]) as $contextId) {
            $contextDef = $container->getDefinition($contextId);
            $arguments = $contextDef->getArguments();
            $listeners[] = array_map(function ($ref) { return (string) $ref; }, $arguments['index_0']);
        }

        $this->assertEquals(array(
            array(),
            array(
                'security.channel_listener',
                'security.logout_listener.secure',
                'security.authentication.listener.x509.secure',
                'security.authentication.listener.remote_user.secure',
                'security.authentication.listener.form.secure',
                'security.authentication.listener.basic.secure',
                'security.authentication.listener.digest.secure',
                'security.authentication.listener.rememberme.secure',
                'security.authentication.listener.anonymous.secure',
                'security.authentication.switchuser_listener.secure',
                'security.access_listener',
            ),
            array(
                'security.channel_listener',
                'security.context_listener.0',
                'security.authentication.listener.basic.host',
                'security.authentication.listener.anonymous.host',
                'security.access_listener',
            ),
        ), $listeners);
    }

    public function testFirewallRequestMatchers()
    {
        $container = $this->getContainer('container1');

        $arguments = $container->getDefinition('security.firewall.map')->getArguments();
        $matchers = array();

        foreach ($arguments[1] as $reference) {
            if ($reference instanceof Reference) {
                $definition = $container->getDefinition((string) $reference);
                $matchers[] = $definition->getArguments();
            }
        }

        $this->assertEquals(array(
            array(
                '/login',
            ),
            array(
                '/test',
                'foo\\.example\\.org',
                array('GET', 'POST'),
            ),
        ), $matchers);
    }

    public function testAccess()
    {
        $container = $this->getContainer('container1');

        $rules = array();
        foreach ($container->getDefinition('security.access_map')->getMethodCalls() as $call) {
            if ($call[0] == 'add') {
                $rules[] = array((string) $call[1][0], $call[1][1], $call[1][2]);
            }
        }

        $matcherIds = array();
        foreach ($rules as $rule) {
            list($matcherId, $attributes, $channel) = $rule;
            $requestMatcher = $container->getDefinition($matcherId);

            $this->assertFalse(isset($matcherIds[$matcherId]));
            $matcherIds[$matcherId] = true;

            $i = count($matcherIds);
            if (1 === $i) {
                $this->assertEquals(array('ROLE_USER'), $attributes);
                $this->assertEquals('https', $channel);
                $this->assertEquals(
                    array('/blog/524', null, array('GET', 'POST')),
                    $requestMatcher->getArguments()
                );
            } elseif (2 === $i) {
                $this->assertEquals(array('IS_AUTHENTICATED_ANONYMOUSLY'), $attributes);
                $this->assertNull($channel);
                $this->assertEquals(
                    array('/blog/.*'),
                    $requestMatcher->getArguments()
                );
            } elseif (3 === $i) {
                $this->assertEquals('IS_AUTHENTICATED_ANONYMOUSLY', $attributes[0]);
                $expression = $container->getDefinition($attributes[1])->getArgument(0);
                $this->assertEquals("token.getUsername() matches '/^admin/'", $expression);
            }
        }
    }

    public function testMerge()
    {
        $container = $this->getContainer('merge');

        $this->assertEquals(array(
            'FOO' => array('MOO'),
            'ADMIN' => array('USER'),
        ), $container->getParameter('security.role_hierarchy.roles'));
    }

    public function testEncoders()
    {
        $container = $this->getContainer('container1');

        $this->assertEquals(array(array(
            'JMS\FooBundle\Entity\User1' => array(
                'class' => new Parameter('security.encoder.plain.class'),
                'arguments' => array(false),
            ),
            'JMS\FooBundle\Entity\User2' => array(
                'class' => new Parameter('security.encoder.digest.class'),
                'arguments' => array('sha1', false, 5),
            ),
            'JMS\FooBundle\Entity\User3' => array(
                'class' => new Parameter('security.encoder.digest.class'),
                'arguments' => array('md5', true, 5000),
            ),
            'JMS\FooBundle\Entity\User4' => new Reference('security.encoder.foo'),
            'JMS\FooBundle\Entity\User5' => array(
                'class' => new Parameter('security.encoder.pbkdf2.class'),
                'arguments' => array('sha1', false, 5, 30),
            ),
            'JMS\FooBundle\Entity\User6' => array(
                'class' => new Parameter('security.encoder.bcrypt.class'),
                'arguments' => array(15),
            ),
        )), $container->getDefinition('security.encoder_factory.generic')->getArguments());
    }

    public function testAcl()
    {
        $container = $this->getContainer('container1');

        $this->assertTrue($container->hasDefinition('security.acl.dbal.provider'));
        $this->assertEquals('security.acl.dbal.provider', (string) $container->getAlias('security.acl.provider'));
    }

    public function testCustomAclProvider()
    {
        $container = $this->getContainer('custom_acl_provider');

        $this->assertFalse($container->hasDefinition('security.acl.dbal.provider'));
        $this->assertEquals('foo', (string) $container->getAlias('security.acl.provider'));
    }

    public function testRememberMeThrowExceptionsDefault()
    {
        $container = $this->getContainer('container1');
        $this->assertTrue($container->getDefinition('security.authentication.listener.rememberme.secure')->getArgument(5));
    }

    public function testRememberMeThrowExceptions()
    {
        $container = $this->getContainer('remember_me_options');
        $service = $container->getDefinition('security.authentication.listener.rememberme.main');
        $this->assertEquals('security.authentication.rememberme.services.persistent.main', $service->getArgument(1));
        $this->assertFalse($service->getArgument(5));
    }

    protected function getContainer($file)
    {
        if (isset(self::$containerCache[$file])) {
            return self::$containerCache[$file];
        }
        $container = new ContainerBuilder();
        $security = new SecurityExtension();
        $container->registerExtension($security);

        $bundle = new SecurityBundle();
        $bundle->build($container); // Attach all default factories
        $this->loadFromFile($container, $file);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return self::$containerCache[$file] = $container;
    }
}

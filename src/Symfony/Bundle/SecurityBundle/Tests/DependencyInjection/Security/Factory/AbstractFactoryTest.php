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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AbstractFactoryTest extends TestCase
{
    public function testCreate()
    {
        list($container, $authProviderId, $listenerId, $entryPointId) = $this->callFactory('foo', array(
            'use_forward' => true,
            'failure_path' => '/foo',
            'success_handler' => 'custom_success_handler',
            'failure_handler' => 'custom_failure_handler',
            'remember_me' => true,
        ), 'user_provider', 'entry_point');

        // auth provider
        $this->assertEquals('auth_provider', $authProviderId);

        // listener
        $this->assertEquals('abstract_listener.foo', $listenerId);
        $this->assertTrue($container->hasDefinition('abstract_listener.foo'));
        $definition = $container->getDefinition('abstract_listener.foo');
        $this->assertEquals(array(
            'index_4' => 'foo',
            'index_5' => new Reference('security.authentication.success_handler.foo.abstract_factory'),
            'index_6' => new Reference('security.authentication.failure_handler.foo.abstract_factory'),
            'index_7' => array(
                'use_forward' => true,
            ),
        ), $definition->getArguments());

        // entry point
        $this->assertEquals('entry_point', $entryPointId, '->create() does not change the default entry point.');
    }

    /**
     * @dataProvider getFailureHandlers
     */
    public function testDefaultFailureHandler($serviceId, $defaultHandlerInjection)
    {
        $options = array(
            'remember_me' => true,
            'login_path' => '/bar',
        );

        if ($serviceId) {
            $options['failure_handler'] = $serviceId;
        }

        list($container, $authProviderId, $listenerId, $entryPointId) = $this->callFactory('foo', $options, 'user_provider', 'entry_point');

        $definition = $container->getDefinition('abstract_listener.foo');
        $arguments = $definition->getArguments();
        $this->assertEquals(new Reference('security.authentication.failure_handler.foo.abstract_factory'), $arguments['index_6']);
        $failureHandler = $container->findDefinition((string) $arguments['index_6']);

        $methodCalls = $failureHandler->getMethodCalls();
        if ($defaultHandlerInjection) {
            $this->assertEquals('setOptions', $methodCalls[0][0]);
            $this->assertEquals(array('login_path' => '/bar'), $methodCalls[0][1][0]);
        } else {
            $this->assertCount(0, $methodCalls);
        }
    }

    public function getFailureHandlers()
    {
        return array(
            array(null, true),
            array('custom_failure_handler', false),
        );
    }

    /**
     * @dataProvider getSuccessHandlers
     */
    public function testDefaultSuccessHandler($serviceId, $defaultHandlerInjection)
    {
        $options = array(
            'remember_me' => true,
            'default_target_path' => '/bar',
        );

        if ($serviceId) {
            $options['success_handler'] = $serviceId;
        }

        list($container, $authProviderId, $listenerId, $entryPointId) = $this->callFactory('foo', $options, 'user_provider', 'entry_point');

        $definition = $container->getDefinition('abstract_listener.foo');
        $arguments = $definition->getArguments();
        $this->assertEquals(new Reference('security.authentication.success_handler.foo.abstract_factory'), $arguments['index_5']);
        $successHandler = $container->findDefinition((string) $arguments['index_5']);
        $methodCalls = $successHandler->getMethodCalls();

        if ($defaultHandlerInjection) {
            $this->assertEquals('setOptions', $methodCalls[0][0]);
            $this->assertEquals(array('default_target_path' => '/bar'), $methodCalls[0][1][0]);
            $this->assertEquals('setProviderKey', $methodCalls[1][0]);
            $this->assertEquals(array('foo'), $methodCalls[1][1]);
        } else {
            $this->assertCount(0, $methodCalls);
        }
    }

    public function getSuccessHandlers()
    {
        return array(
            array(null, true),
            array('custom_success_handler', false),
        );
    }

    protected function callFactory($id, $config, $userProviderId, $defaultEntryPointId)
    {
        $factory = $this->getMockForAbstractClass('Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory', array());

        $factory
            ->expects($this->once())
            ->method('createAuthProvider')
            ->will($this->returnValue('auth_provider'))
        ;
        $factory
            ->expects($this->atLeastOnce())
            ->method('getListenerId')
            ->will($this->returnValue('abstract_listener'))
        ;
        $factory
            ->expects($this->any())
            ->method('getKey')
            ->will($this->returnValue('abstract_factory'))
        ;

        $container = new ContainerBuilder();
        $container->register('auth_provider');
        $container->register('custom_success_handler');
        $container->register('custom_failure_handler');

        list($authProviderId, $listenerId, $entryPointId) = $factory->create($container, $id, $config, $userProviderId, $defaultEntryPointId);

        return array($container, $authProviderId, $listenerId, $entryPointId);
    }
}

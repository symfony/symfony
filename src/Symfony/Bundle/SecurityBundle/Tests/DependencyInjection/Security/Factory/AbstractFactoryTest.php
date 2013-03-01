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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AbstractFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        list($container,
             $authProviderId,
             $listenerId,
             $entryPointId
         ) = $this->callFactory('foo', array('use_forward' => true, 'failure_path' => '/foo', 'success_handler' => 'qux', 'failure_handler' => 'bar', 'remember_me' => true), 'user_provider', 'entry_point');

        // auth provider
        $this->assertEquals('auth_provider', $authProviderId);

        // listener
        $this->assertEquals('abstract_listener.foo', $listenerId);
        $this->assertTrue($container->hasDefinition('abstract_listener.foo'));
        $definition = $container->getDefinition('abstract_listener.foo');
        $this->assertEquals(array(
            'index_4' => 'foo',
            'index_5' => new Reference('qux'),
            'index_6' => new Reference('bar'),
            'index_7' => array(
                'use_forward'                    => true,
            ),
        ), $definition->getArguments());

        // entry point
        $this->assertEquals('entry_point', $entryPointId, '->create() does not change the default entry point.');
    }

    public function testDefaultFailureHandler()
    {
        list($container,
             $authProviderId,
             $listenerId,
             $entryPointId
         ) = $this->callFactory('foo', array('remember_me' => true), 'user_provider', 'entry_point');

        $definition = $container->getDefinition('abstract_listener.foo');
        $arguments = $definition->getArguments();
        $this->assertEquals(new Reference('security.authentication.failure_handler.foo.abstract_factory'), $arguments['index_6']);
    }

    public function testDefaultSuccessHandler()
    {
        list($container,
             $authProviderId,
             $listenerId,
             $entryPointId
         ) = $this->callFactory('foo', array('remember_me' => true), 'user_provider', 'entry_point');

        $definition = $container->getDefinition('abstract_listener.foo');
        $arguments = $definition->getArguments();
        $this->assertEquals(new Reference('security.authentication.success_handler.foo.abstract_factory'), $arguments['index_5']);
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

        list($authProviderId,
             $listenerId,
             $entryPointId
         ) = $factory->create($container, $id, $config, $userProviderId, $defaultEntryPointId);

        return array($container, $authProviderId, $listenerId, $entryPointId);
    }
}

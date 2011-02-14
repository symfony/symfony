<?php

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AbstractFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $factory = $this->getFactory();

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

        $container = new ContainerBuilder();
        $container->register('auth_provider');

        list($authProviderId,
             $listenerId,
             $entryPointId
        ) = $factory->create($container, 'foo', array('use_forward' => true, 'failure_path' => '/foo', 'success_handler' => 'foo', 'remember_me' => true), 'user_provider', 'entry_point');

        // auth provider
        $this->assertEquals('auth_provider', $authProviderId);

        // listener
        $this->assertEquals('abstract_listener.foo', $listenerId);
        $this->assertTrue($container->hasDefinition('abstract_listener.foo'));
        $definition = $container->getDefinition('abstract_listener.foo');
        $this->assertEquals(array(
            'index_3' => 'foo',
            'index_4' => array(
                'use_forward'                    => true,
                'failure_path'                   => '/foo',
            ),
            'index_5' => new Reference('foo'),
        ), $definition->getArguments());

        // entry point
        $this->assertEquals('entry_point', $entryPointId, '->create() does not change the default entry point.');
    }

    protected function getFactory()
    {
        return $this->getMockForAbstractClass('Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory', array());
    }
}
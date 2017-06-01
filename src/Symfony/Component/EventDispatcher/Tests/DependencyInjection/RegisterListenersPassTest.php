<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;

class RegisterListenersPassTest extends TestCase
{
    /**
     * Tests that event subscribers not implementing EventSubscriberInterface
     * trigger an exception.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testEventSubscriberWithoutInterface()
    {
        // one service, not implementing any interface
        $services = array(
            'my_event_subscriber' => array(0 => array()),
        );

        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $definition->expects($this->atLeastOnce())
            ->method('getClass')
            ->will($this->returnValue('stdClass'));

        $builder = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'getDefinition'))->getMock();
        $builder->expects($this->any())
            ->method('hasDefinition')
            ->will($this->returnValue(true));

        // We don't test kernel.event_listener here
        $builder->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->onConsecutiveCalls(array(), $services));

        $builder->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->will($this->returnValue($definition));

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($builder);
    }

    public function testValidEventSubscriber()
    {
        $services = array(
            'my_event_subscriber' => array(0 => array()),
        );

        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $definition->expects($this->atLeastOnce())
            ->method('getClass')
            ->will($this->returnValue('Symfony\Component\EventDispatcher\Tests\DependencyInjection\SubscriberService'));

        $builder = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'getDefinition', 'findDefinition'))->getMock();
        $builder->expects($this->any())
            ->method('hasDefinition')
            ->will($this->returnValue(true));

        // We don't test kernel.event_listener here
        $builder->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->onConsecutiveCalls(array(), $services));

        $builder->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->will($this->returnValue($definition));

        $builder->expects($this->atLeastOnce())
            ->method('findDefinition')
            ->will($this->returnValue($definition));

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($builder);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "foo" tagged "kernel.event_listener" must not be abstract.
     */
    public function testAbstractEventListener()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setAbstract(true)->addTag('kernel.event_listener', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "foo" tagged "kernel.event_subscriber" must not be abstract.
     */
    public function testAbstractEventSubscriber()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setAbstract(true)->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testEventSubscriberResolvableClassName()
    {
        $container = new ContainerBuilder();

        $container->setParameter('subscriber.class', 'Symfony\Component\EventDispatcher\Tests\DependencyInjection\SubscriberService');
        $container->register('foo', '%subscriber.class%')->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expectedCalls = array(
            array(
                'addListener',
                array(
                    'event',
                    array(new ServiceClosureArgument(new Reference('foo')), 'onEvent'),
                    0,
                ),
            ),
        );
        $this->assertEquals($expectedCalls, $definition->getMethodCalls());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage You have requested a non-existent parameter "subscriber.class"
     */
    public function testEventSubscriberUnresolvableClassName()
    {
        $container = new ContainerBuilder();
        $container->register('foo', '%subscriber.class%')->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }
}

class SubscriberService implements \Symfony\Component\EventDispatcher\EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'event' => 'onEvent',
        );
    }
}

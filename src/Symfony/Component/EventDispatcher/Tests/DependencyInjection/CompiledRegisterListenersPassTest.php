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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\CompiledRegisterListenersPass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CompiledRegisterListenersPassTest extends \PHPUnit_Framework_TestCase
{
    public function testPassAddsConstructorArgument()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', 'stdClass')
            ->setArguments(array('foo', 'bar'));

        $registerListenersPass = new CompiledRegisterListenersPass();
        $registerListenersPass->process($container);

        $expected_arguments = array('foo', 'bar', array());
        $this->assertSame($expected_arguments, $definition->getArguments());
    }

    public function testPassAddsTaggedListenersAndSubscribers()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('event_dispatcher', 'stdClass');

        $container->register('test_subscriber', 'Symfony\Component\EventDispatcher\Tests\DependencyInjection\CompiledSubscriberService')
            ->addTag('kernel.event_subscriber');

        $container->register('test_listener', 'stdObject')
            ->addTag('kernel.event_listener', array(
                'event' => 'test_event.multiple_listeners',
                'method' => 'methodWithMediumPriority',
                'priority' => 32,
            ));

        $registerListenersPass = new CompiledRegisterListenersPass();
        $registerListenersPass->process($container);

        $expected_listeners = array(
            'test_event.multiple_listeners' => array(
                128 => array(
                    array(
                        'service' => array('id' => 'test_subscriber', 'method' => 'methodWithHighestPriority'),
                    ),
                ),
                32 => array(
                    array(
                        'service' => array('id' => 'test_listener', 'method' => 'methodWithMediumPriority'),
                    ),
                ),
                0 => array(
                    array(
                        'service' => array('id' => 'test_subscriber', 'method' => 'methodWithoutPriority'),
                    ),
                ),
            ),
            'test_event.single_listener_with_priority' => array(
                64 => array(
                    array(
                        'service' => array('id' => 'test_subscriber', 'method' => 'methodWithHighPriority'),
                    ),
                ),
            ),
            'test_event.single_listener_without_priority' => array(
                0 => array(
                    array(
                        'service' => array('id' => 'test_subscriber', 'method' => 'methodWithoutPriority'),
                    ),
                ),
            ),
        );
        $this->assertSame(array($expected_listeners), $definition->getArguments());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "foo" must implement interface "Symfony\Component\EventDispatcher\EventSubscriberInterface".
     */
    public function testEventSubscriberWithoutInterface()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new CompiledRegisterListenersPass();
        $registerListenersPass->process($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "foo" must be public as event listeners are lazy-loaded.
     */
    public function testPrivateEventListener()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setPublic(false)->addTag('kernel.event_listener', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new CompiledRegisterListenersPass();
        $registerListenersPass->process($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "foo" must be public as event subscribers are lazy-loaded.
     */
    public function testPrivateEventSubscriber()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setPublic(false)->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new CompiledRegisterListenersPass();
        $registerListenersPass->process($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "foo" must not be abstract as event listeners are lazy-loaded.
     */
    public function testAbstractEventListener()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setAbstract(true)->addTag('kernel.event_listener', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new CompiledRegisterListenersPass();
        $registerListenersPass->process($container);
    }
}

class CompiledSubscriberService implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'test_event.multiple_listeners' => array(
                array('methodWithHighestPriority', 128),
                array('methodWithoutPriority'),
            ),
            'test_event.single_listener_with_priority' => array(
                array('methodWithHighPriority', 64),
            ),
            'test_event.single_listener_without_priority' => array(
                array('methodWithoutPriority'),
            ),
        );
    }
}

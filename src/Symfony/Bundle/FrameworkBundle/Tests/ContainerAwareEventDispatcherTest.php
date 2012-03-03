<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Bundle\FrameworkBundle\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\DependencyInjection\Scope;

class ContainerAwareEventDispatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testAddAListenerService()
    {
        $event = new Event();

        $service = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'));

        $dispatcher->dispatch('onEvent', $event);
    }

    public function testPreventDuplicateListenerService()
    {
        $event = new Event();

        $service = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'), 5);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'), 10);

        $dispatcher->dispatch('onEvent', $event);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTriggerAListenerServiceOutOfScope()
    {
        $service = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $scope = new Scope('scope');
        $container = new Container();
        $container->addScope($scope);
        $container->enterScope('scope');

        $container->set('service.listener', $service, 'scope');

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'));

        $container->leaveScope('scope');
        $dispatcher->dispatch('onEvent');
    }

    public function testReEnteringAScope()
    {
        $event = new Event();

        $service1 = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $service1
            ->expects($this->exactly(2))
            ->method('onEvent')
            ->with($event)
        ;

        $scope = new Scope('scope');
        $container = new Container();
        $container->addScope($scope);
        $container->enterScope('scope');

        $container->set('service.listener', $service1, 'scope');

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'));
        $dispatcher->dispatch('onEvent', $event);

        $service2 = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $service2
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $container->enterScope('scope');
        $container->set('service.listener', $service2, 'scope');

        $dispatcher->dispatch('onEvent', $event);

        $container->leaveScope('scope');

        $dispatcher->dispatch('onEvent');
    }

    public function testHasListenersOnLazyLoad()
    {
        $event = new Event();

        $service = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'));

        $this->assertTrue($dispatcher->hasListeners());

        if ($dispatcher->hasListeners('onEvent')) {
            $dispatcher->dispatch('onEvent');
        }
    }

    public function testGetListenersOnLazyLoad()
    {
        $event = new Event();

        $service = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'));

        $listeners = $dispatcher->getListeners();

        $this->assertTrue(isset($listeners['onEvent']));

        $this->assertEquals(1, count($dispatcher->getListeners('onEvent')));
    }

    public function testRemoveAfterDispatch()
    {
        $event = new Event();

        $service = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'));

        $dispatcher->dispatch('onEvent', new Event());
        $dispatcher->removeListener('onEvent', array($container->get('service.listener'), 'onEvent'));
        $this->assertFalse($dispatcher->hasListeners('onEvent'));
    }

    public function testRemoveBeforeDispatch()
    {
        $event = new Event();

        $service = $this->getMock('Symfony\Bundle\FrameworkBundle\Tests\Service');

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', array('service.listener', 'onEvent'));

        $dispatcher->removeListener('onEvent', array($container->get('service.listener'), 'onEvent'));
        $this->assertFalse($dispatcher->hasListeners('onEvent'));
    }
}

class Service
{
    function onEvent(Event $e)
    {
    }
}

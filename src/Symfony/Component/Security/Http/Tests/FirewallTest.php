<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class FirewallTest extends \PHPUnit_Framework_TestCase
{
    public function testOnKernelRequestRegistersExceptionListener()
    {
        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $listener = $this->getMock('Symfony\Component\Security\Http\Firewall\ExceptionListener', array(), array(), '', false);
        $listener
            ->expects($this->once())
            ->method('register')
            ->with($this->equalTo($dispatcher))
        ;

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request', array(), array(), '', false, false);

        $map = $this->getMock('Symfony\Component\Security\Http\FirewallMapInterface');
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->will($this->returnValue(array(array(), $listener)))
        ;

        $event = new GetResponseEvent($this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface'), $request, HttpKernelInterface::MASTER_REQUEST);

        $firewall = new Firewall($map, $dispatcher);
        $firewall->onKernelRequest($event);
    }

    public function testOnKernelRequestStopsWhenThereIsAResponse()
    {
        $first = $this->getMock('Symfony\Component\Security\Http\Firewall\ListenerInterface');
        $first
            ->expects($this->once())
            ->method('handle')
        ;

        $second = $this->getMock('Symfony\Component\Security\Http\Firewall\ListenerInterface');
        $second
            ->expects($this->never())
            ->method('handle')
        ;

        $map = $this->getMock('Symfony\Component\Security\Http\FirewallMapInterface');
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->will($this->returnValue(array(array($first, $second), null)))
        ;

        $event = $this->getMock(
            'Symfony\Component\HttpKernel\Event\GetResponseEvent',
            array('hasResponse'),
            array(
                $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface'),
                $this->getMock('Symfony\Component\HttpFoundation\Request', array(), array(), '', false, false),
                HttpKernelInterface::MASTER_REQUEST,
            )
        );
        $event
            ->expects($this->once())
            ->method('hasResponse')
            ->will($this->returnValue(true))
        ;

        $firewall = new Firewall($map, $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
        $firewall->onKernelRequest($event);
    }

    public function testOnKernelRequestWithSubRequest()
    {
        $map = $this->getMock('Symfony\Component\Security\Http\FirewallMapInterface');
        $map
            ->expects($this->never())
            ->method('getListeners')
        ;

        $event = new GetResponseEvent(
            $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface'),
            $this->getMock('Symfony\Component\HttpFoundation\Request'),
            HttpKernelInterface::SUB_REQUEST
        );

        $firewall = new Firewall($map, $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
        $firewall->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testOnKernelFinishRequestUnregistersExceptionsListeners()
    {
        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $listener = $this->getMock('Symfony\Component\Security\Http\Firewall\ExceptionListener', array(), array(), '', false);
        $listener
            ->expects($this->once())
            ->method('register')
            ->with($this->equalTo($dispatcher))
        ;
        $listener
            ->expects($this->once())
            ->method('unregister')
            ->with($this->equalTo($dispatcher))
        ;

        $request = new Request();

        $map = $this->getMock('Symfony\Component\Security\Http\FirewallMapInterface');
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->will($this->returnValue(array(array(), $listener)))
        ;

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $getResponseEvent = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
        $finishRequestEvent = new FinishRequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $firewall = new Firewall($map, $dispatcher);
        $firewall->onKernelRequest($getResponseEvent);
        $firewall->onKernelFinishRequest($finishRequestEvent);
    }

    public function testGetSubscribedEvents()
    {
        $map = $this->getMock('Symfony\Component\Security\Http\FirewallMapInterface');
        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $firewall = new Firewall($map, $dispatcher);
        $subscription = $firewall->getSubscribedEvents();

        $this->assertTrue(array_key_exists(KernelEvents::REQUEST, $subscription));
        $this->assertTrue(array_key_exists(KernelEvents::FINISH_REQUEST, $subscription));
    }
}

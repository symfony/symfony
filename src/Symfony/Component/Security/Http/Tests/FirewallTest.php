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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\FirewallMapInterface;

class FirewallTest extends TestCase
{
    public function testOnKernelRequestRegistersExceptionListener()
    {
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();

        $listener = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ExceptionListener')->disableOriginalConstructor()->getMock();
        $listener
            ->expects($this->once())
            ->method('register')
            ->with($this->equalTo($dispatcher))
        ;

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $map = $this->getMockBuilder('Symfony\Component\Security\Http\FirewallMapInterface')->getMock();
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->will($this->returnValue(array(array(), $listener, null)))
        ;

        $event = new GetResponseEvent($this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);

        $firewall = new Firewall($map, $dispatcher);
        $firewall->onKernelRequest($event);
    }

    public function testOnKernelRequestStopsWhenThereIsAResponse()
    {
        $response = new Response();

        $first = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock();
        $first
            ->expects($this->once())
            ->method('handle')
        ;

        $second = $this->getMockBuilder('Symfony\Component\Security\Http\Firewall\ListenerInterface')->getMock();
        $second
            ->expects($this->never())
            ->method('handle')
        ;

        $map = $this->getMockBuilder('Symfony\Component\Security\Http\FirewallMapInterface')->getMock();
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->will($this->returnValue(array(array($first, $second), null, null)))
        ;

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->setMethods(array('hasResponse'))
            ->setConstructorArgs(array(
                $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(),
                $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock(),
                HttpKernelInterface::MASTER_REQUEST,
            ))
            ->getMock()
        ;
        $event
            ->expects($this->at(0))
            ->method('hasResponse')
            ->will($this->returnValue(true))
        ;

        $firewall = new Firewall($map, $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock());
        $firewall->onKernelRequest($event);
    }

    public function testOnKernelRequestWithSubRequest()
    {
        $map = $this->getMockBuilder('Symfony\Component\Security\Http\FirewallMapInterface')->getMock();
        $map
            ->expects($this->never())
            ->method('getListeners')
        ;

        $event = new GetResponseEvent(
            $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(),
            $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock(),
            HttpKernelInterface::SUB_REQUEST
        );

        $firewall = new Firewall($map, $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock());
        $firewall->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    /**
     * @group legacy
     * @expectedDeprecation Not returning an array of 3 elements from Symfony\Component\Security\Http\FirewallMapInterface::getListeners() is deprecated since Symfony 4.2, the 3rd element must be an instance of Symfony\Component\Security\Http\Firewall\LogoutListener or null.
     */
    public function testMissingLogoutListener()
    {
        $dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $listener = $this->getMockBuilder(ExceptionListener::class)->disableOriginalConstructor()->getMock();
        $listener
            ->expects($this->once())
            ->method('register')
            ->with($this->equalTo($dispatcher))
        ;

        $request = new Request();

        $map = $this->getMockBuilder(FirewallMapInterface::class)->getMock();
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->willReturn(array(array(), $listener))
        ;

        $firewall = new Firewall($map, $dispatcher);
        $firewall->onKernelRequest(new GetResponseEvent($this->getMockBuilder(HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST));
    }
}

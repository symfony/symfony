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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Firewall;

class FirewallTest extends \PHPUnit_Framework_TestCase
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
            ->will($this->returnValue(array(array(), $listener)))
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
            ->will($this->returnValue(array(array($first, $second), null)))
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
            ->expects($this->once())
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
}

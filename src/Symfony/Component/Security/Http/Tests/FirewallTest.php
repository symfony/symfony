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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Firewall;

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
            ->willReturn([[], $listener, null])
        ;

        $event = new RequestEvent($this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);

        $firewall = new Firewall($map, $dispatcher);
        $firewall->onKernelRequest($event);
    }

    public function testOnKernelRequestStopsWhenThereIsAResponse()
    {
        $called = [];

        $first = function () use (&$called) {
            $called[] = 1;
        };

        $second = function () use (&$called) {
            $called[] = 2;
        };

        $map = $this->getMockBuilder('Symfony\Component\Security\Http\FirewallMapInterface')->getMock();
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->willReturn([[$first, $second], null, null])
        ;

        $event = $this->getMockBuilder(RequestEvent::class)
            ->setMethods(['hasResponse'])
            ->setConstructorArgs([
                $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(),
                $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock(),
                HttpKernelInterface::MASTER_REQUEST,
            ])
            ->getMock()
        ;
        $event
            ->expects($this->at(0))
            ->method('hasResponse')
            ->willReturn(true)
        ;

        $firewall = new Firewall($map, $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock());
        $firewall->onKernelRequest($event);

        $this->assertSame([1], $called);
    }

    public function testOnKernelRequestWithSubRequest()
    {
        $map = $this->getMockBuilder('Symfony\Component\Security\Http\FirewallMapInterface')->getMock();
        $map
            ->expects($this->never())
            ->method('getListeners')
        ;

        $event = new RequestEvent(
            $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(),
            $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock(),
            HttpKernelInterface::SUB_REQUEST
        );

        $firewall = new Firewall($map, $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock());
        $firewall->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }
}

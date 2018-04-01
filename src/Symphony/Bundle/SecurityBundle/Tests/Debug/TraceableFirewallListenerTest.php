<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symphony\Bundle\SecurityBundle\Security\FirewallMap;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\Security\Http\Firewall\ListenerInterface;
use Symphony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symphony\Component\VarDumper\Caster\ClassStub;

/**
 * @group time-sensitive
 */
class TraceableFirewallListenerTest extends TestCase
{
    public function testOnKernelRequestRecordsListeners()
    {
        $request = new Request();
        $event = new GetResponseEvent($this->getMockBuilder(HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $event->setResponse($response = new Response());
        $listener = $this->getMockBuilder(ListenerInterface::class)->getMock();
        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($event);
        $firewallMap = $this
            ->getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects($this->once())
            ->method('getListeners')
            ->with($request)
            ->willReturn(array(array($listener), null));

        $firewall = new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator());
        $firewall->onKernelRequest($event);

        $listeners = $firewall->getWrappedListeners();
        $this->assertCount(1, $listeners);
        $this->assertSame($response, $listeners[0]['response']);
        $this->assertInstanceOf(ClassStub::class, $listeners[0]['stub']);
        $this->assertSame(get_class($listener), (string) $listeners[0]['stub']);
    }
}

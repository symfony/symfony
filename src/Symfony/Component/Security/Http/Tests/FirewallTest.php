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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\FirewallMapInterface;

class FirewallTest extends TestCase
{
    public function testOnKernelRequestRegistersExceptionListener()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $listener = $this->createMock(ExceptionListener::class);
        $listener
            ->expects($this->once())
            ->method('register')
            ->with($this->equalTo($dispatcher))
        ;

        $request = $this->createMock(Request::class);

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->willReturn([[], $listener, null])
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

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

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->willReturn([[$first, $second], null, null])
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse(new Response());

        $firewall = new Firewall($map, $this->createMock(EventDispatcherInterface::class));
        $firewall->onKernelRequest($event);

        $this->assertSame([1], $called);
    }

    public function testOnKernelRequestWithSubRequest()
    {
        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->never())
            ->method('getListeners')
        ;

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $this->createMock(Request::class),
            HttpKernelInterface::SUB_REQUEST
        );

        $firewall = new Firewall($map, $this->createMock(EventDispatcherInterface::class));
        $firewall->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }
}

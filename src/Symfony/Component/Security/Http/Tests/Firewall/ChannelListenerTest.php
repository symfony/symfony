<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\ChannelListener;

class ChannelListenerTest extends TestCase
{
    public function testHandleWithNotSecuredRequestAndHttpChannel()
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->any())
            ->method('isSecure')
            ->willReturn(false)
        ;

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[], 'http'])
        ;

        $entryPoint = $this->createMock(AuthenticationEntryPointInterface::class);
        $entryPoint
            ->expects($this->never())
            ->method('start')
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap, $entryPoint);
        $listener($event);

        $this->assertNull($event->getResponse());
    }

    public function testHandleWithSecuredRequestAndHttpsChannel()
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->any())
            ->method('isSecure')
            ->willReturn(true)
        ;

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[], 'https'])
        ;

        $entryPoint = $this->createMock(AuthenticationEntryPointInterface::class);
        $entryPoint
            ->expects($this->never())
            ->method('start')
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap, $entryPoint);
        $listener($event);

        $this->assertNull($event->getResponse());
    }

    public function testHandleWithNotSecuredRequestAndHttpsChannel()
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->any())
            ->method('isSecure')
            ->willReturn(false)
        ;

        $response = new Response();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[], 'https'])
        ;

        $entryPoint = $this->createMock(AuthenticationEntryPointInterface::class);
        $entryPoint
            ->expects($this->once())
            ->method('start')
            ->with($this->equalTo($request))
            ->willReturn($response)
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap, $entryPoint);
        $listener($event);

        $this->assertSame($response, $event->getResponse());
    }

    public function testHandleWithSecuredRequestAndHttpChannel()
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->any())
            ->method('isSecure')
            ->willReturn(true)
        ;

        $response = new Response();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[], 'http'])
        ;

        $entryPoint = $this->createMock(AuthenticationEntryPointInterface::class);
        $entryPoint
            ->expects($this->once())
            ->method('start')
            ->with($this->equalTo($request))
            ->willReturn($response)
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap, $entryPoint);
        $listener($event);

        $this->assertSame($response, $event->getResponse());
    }
}

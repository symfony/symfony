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
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\ChannelListener;

class ChannelListenerTest extends TestCase
{
    public function testHandleWithNotSecuredRequestAndHttpChannel()
    {
        $request = self::createMock(Request::class);
        $request
            ->expects(self::any())
            ->method('isSecure')
            ->willReturn(false)
        ;

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[], 'http'])
        ;

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap);
        $listener($event);

        self::assertNull($event->getResponse());
    }

    public function testHandleWithSecuredRequestAndHttpsChannel()
    {
        $request = self::createMock(Request::class);
        $request
            ->expects(self::any())
            ->method('isSecure')
            ->willReturn(true)
        ;

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[], 'https'])
        ;

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap);
        $listener($event);

        self::assertNull($event->getResponse());
    }

    public function testHandleWithNotSecuredRequestAndHttpsChannel()
    {
        $request = self::createMock(Request::class);
        $request
            ->expects(self::any())
            ->method('isSecure')
            ->willReturn(false)
        ;

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[], 'https'])
        ;

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap);
        $listener($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertEquals('https://', $response->getTargetUrl());
    }

    public function testHandleWithSecuredRequestAndHttpChannel()
    {
        $request = self::createMock(Request::class);
        $request
            ->expects(self::any())
            ->method('isSecure')
            ->willReturn(true)
        ;

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[], 'http'])
        ;

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap);
        $listener($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertEquals('http://', $response->getTargetUrl());
    }

    public function testSupportsWithoutHeaders()
    {
        $request = self::createMock(Request::class);
        $request
            ->expects(self::any())
            ->method('isSecure')
            ->willReturn(false)
        ;
        $request->headers = new HeaderBag();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[], 'https'])
        ;

        $listener = new ChannelListener($accessMap);

        self::assertTrue($listener->supports($request));
    }

    /**
     * @group legacy
     */
    public function testLegacyHandleWithEntryPoint()
    {
        $request = self::createMock(Request::class);
        $request
            ->expects(self::any())
            ->method('isSecure')
            ->willReturn(false)
        ;

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[], 'https'])
        ;

        $response = new RedirectResponse('/redirected');

        $entryPoint = self::createMock(AuthenticationEntryPointInterface::class);
        $entryPoint
            ->expects(self::once())
            ->method('start')
            ->with(self::equalTo($request))
            ->willReturn($response)
        ;

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelListener($accessMap, $entryPoint);
        $listener($event);

        self::assertSame($response, $event->getResponse());
    }
}

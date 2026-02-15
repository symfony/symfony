<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\SameOriginCsrfListener;

class SameOriginCsrfListenerTest extends TestCase
{
    private SameOriginCsrfListener $sameOriginCsrfListener;

    protected function setUp(): void
    {
        $this->sameOriginCsrfListener = new SameOriginCsrfListener('csrf-token');
    }

    public function testOnKernelResponseClearsCookies()
    {
        $request = new Request([], [], ['csrf-token' => 2], ['csrf-token_test' => 'csrf-token']);
        $response = new Response();
        $eventMainRequest = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->sameOriginCsrfListener->onKernelResponse($eventMainRequest);

        $this->assertTrue($response->headers->has('Set-Cookie'));
    }

    public function testOnKernelResponsePersistsStrategy()
    {
        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn(true);

        $request = new Request();
        $request->setSession($session);
        $request->attributes->set('csrf-token', 2 << 8);

        $response = new Response();
        $eventMainRequest = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $session->expects($this->once())->method('set')->with('csrf-token', 2 << 8);

        $this->sameOriginCsrfListener->onKernelResponse($eventMainRequest);
    }

    public function testOnKernelResponseDoesNothingIfSessionNotStarted()
    {
        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn(false);

        $request = new Request();
        $request->setSession($session);
        $request->attributes->set('csrf-token', 2 << 8);

        $response = new Response();
        $eventMainRequest = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $session->expects($this->never())->method('set');

        $this->sameOriginCsrfListener->onKernelResponse($eventMainRequest);
    }

    public function testOnKernelResponseIgnoresSubRequests()
    {
        $request = new Request([], [], ['csrf-token' => 2]);
        $response = new Response();
        $eventSubRequest = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->sameOriginCsrfListener->onKernelResponse($eventSubRequest);

        $this->assertFalse($response->headers->has('Set-Cookie'));
    }
}

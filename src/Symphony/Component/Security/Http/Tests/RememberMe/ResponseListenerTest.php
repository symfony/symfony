<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\RememberMe;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\Security\Http\RememberMe\ResponseListener;
use Symphony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpFoundation\Cookie;
use Symphony\Component\HttpKernel\KernelEvents;

class ResponseListenerTest extends TestCase
{
    public function testRememberMeCookieIsSentWithResponse()
    {
        $cookie = new Cookie('rememberme');

        $request = $this->getRequest(array(
            RememberMeServicesInterface::COOKIE_ATTR_NAME => $cookie,
        ));

        $response = $this->getResponse();
        $response->headers->expects($this->once())->method('setCookie')->with($cookie);

        $listener = new ResponseListener();
        $listener->onKernelResponse($this->getEvent($request, $response));
    }

    public function testRememberMeCookieIsNotSendWithResponseForSubRequests()
    {
        $cookie = new Cookie('rememberme');

        $request = $this->getRequest(array(
            RememberMeServicesInterface::COOKIE_ATTR_NAME => $cookie,
        ));

        $response = $this->getResponse();
        $response->headers->expects($this->never())->method('setCookie');

        $listener = new ResponseListener();
        $listener->onKernelResponse($this->getEvent($request, $response, HttpKernelInterface::SUB_REQUEST));
    }

    public function testRememberMeCookieIsNotSendWithResponse()
    {
        $request = $this->getRequest();

        $response = $this->getResponse();
        $response->headers->expects($this->never())->method('setCookie');

        $listener = new ResponseListener();
        $listener->onKernelResponse($this->getEvent($request, $response));
    }

    public function testItSubscribesToTheOnKernelResponseEvent()
    {
        $listener = new ResponseListener();

        $this->assertSame(array(KernelEvents::RESPONSE => 'onKernelResponse'), ResponseListener::getSubscribedEvents());
    }

    private function getRequest(array $attributes = array())
    {
        $request = new Request();

        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }

        return $request;
    }

    private function getResponse()
    {
        $response = new Response();
        $response->headers = $this->getMockBuilder('Symphony\Component\HttpFoundation\ResponseHeaderBag')->getMock();

        return $response;
    }

    private function getEvent($request, $response, $type = HttpKernelInterface::MASTER_REQUEST)
    {
        $event = $this->getMockBuilder('Symphony\Component\HttpKernel\Event\FilterResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())->method('getRequest')->will($this->returnValue($request));
        $event->expects($this->any())->method('isMasterRequest')->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST === $type));
        $event->expects($this->any())->method('getResponse')->will($this->returnValue($response));

        return $event;
    }
}

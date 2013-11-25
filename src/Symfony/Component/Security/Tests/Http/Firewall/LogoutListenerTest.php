<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

class LogoutListenerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Form\Form')) {
            $this->markTestSkipped('The "Form" component is not available');
        }

        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        if (!class_exists('Symfony\Component\HttpKernel\HttpKernel')) {
            $this->markTestSkipped('The "HttpKernel" component is not available');
        }
    }

    public function testHandleUnmatchedPath()
    {
        list($listener, $context, $httpUtils, $options) = $this->getListener();

        list($event, $request) = $this->getGetResponseEvent();

        $event->expects($this->never())
            ->method('setResponse');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->will($this->returnValue(false));

        $listener->handle($event);
    }

    public function testHandleMatchedPathWithSuccessHandlerAndCsrfValidation()
    {
        $successHandler = $this->getSuccessHandler();
        $csrfProvider = $this->getCsrfProvider();

        list($listener, $context, $httpUtils, $options) = $this->getListener($successHandler, $csrfProvider);

        list($event, $request) = $this->getGetResponseEvent();

        $request->query->set('_csrf_token', $csrfToken = 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->will($this->returnValue(true));

        $csrfProvider->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('logout', $csrfToken)
            ->will($this->returnValue(true));

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->will($this->returnValue($response = new Response()));

        $context->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token = $this->getToken()));

        $handler = $this->getHandler();
        $handler->expects($this->once())
            ->method('logout')
            ->with($request, $response, $token);

        $context->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $listener->addHandler($handler);

        $listener->handle($event);
    }

    public function testHandleMatchedPathWithoutSuccessHandlerAndCsrfValidation()
    {
        $successHandler = $this->getSuccessHandler();

        list($listener, $context, $httpUtils, $options) = $this->getListener($successHandler);

        list($event, $request) = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->will($this->returnValue(true));

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->will($this->returnValue($response = new Response()));

        $context->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token = $this->getToken()));

        $handler = $this->getHandler();
        $handler->expects($this->once())
            ->method('logout')
            ->with($request, $response, $token);

        $context->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $listener->addHandler($handler);

        $listener->handle($event);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSuccessHandlerReturnsNonResponse()
    {
        $successHandler = $this->getSuccessHandler();

        list($listener, $context, $httpUtils, $options) = $this->getListener($successHandler);

        list($event, $request) = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->will($this->returnValue(true));

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->will($this->returnValue(null));

        $listener->handle($event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\LogoutException
     */
    public function testCsrfValidationFails()
    {
        $csrfProvider = $this->getCsrfProvider();

        list($listener, $context, $httpUtils, $options) = $this->getListener(null, $csrfProvider);

        list($event, $request) = $this->getGetResponseEvent();

        $request->query->set('_csrf_token', $csrfToken = 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->will($this->returnValue(true));

        $csrfProvider->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('logout', $csrfToken)
            ->will($this->returnValue(false));

        $listener->handle($event);
    }

    private function getCsrfProvider()
    {
        return $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface');
    }

    private function getContext()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\SecurityContext')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getGetResponseEvent()
    {
        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request = new Request()));

        return array($event, $request);
    }

    private function getHandler()
    {
        return $this->getMock('Symfony\Component\Security\Http\Logout\LogoutHandlerInterface');
    }

    private function getHttpUtils()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\HttpUtils')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getListener($successHandler = null, $csrfProvider = null)
    {
        $listener = new LogoutListener(
            $context = $this->getContext(),
            $httpUtils = $this->getHttpUtils(),
            $successHandler ?: $this->getSuccessHandler(),
            $options = array(
                'csrf_parameter' => '_csrf_token',
                'intention'      => 'logout',
                'logout_path'    => '/logout',
                'target_url'     => '/',
            ),
            $csrfProvider
        );

        return array($listener, $context, $httpUtils, $options);
    }

    private function getSuccessHandler()
    {
        return $this->getMock('Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface');
    }

    private function getToken()
    {
        return $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
    }
}

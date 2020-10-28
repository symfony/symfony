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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Firewall\LogoutListener;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

class LogoutListenerTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testHandleUnmatchedPath()
    {
        $dispatcher = $this->getEventDispatcher();
        [$listener, , $httpUtils, $options] = $this->getListener($dispatcher);

        [$event, $request] = $this->getGetResponseEvent();

        $logoutEventDispatched = false;
        $dispatcher->addListener(LogoutEvent::class, function (LogoutEvent $event) use (&$logoutEventDispatched) {
            $logoutEventDispatched = true;
        });

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(false);

        $listener($event);

        $this->assertFalse($logoutEventDispatched, 'LogoutEvent should not have been dispatched.');
    }

    public function testHandleMatchedPathWithCsrfValidation()
    {
        $tokenManager = $this->getTokenManager();
        $dispatcher = $this->getEventDispatcher();

        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($dispatcher, $tokenManager);

        [$event, $request] = $this->getGetResponseEvent();

        $request->query->set('_csrf_token', 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $response = new Response();
        $dispatcher->addListener(LogoutEvent::class, function (LogoutEvent $event) use ($response) {
            $event->setResponse($response);
        });

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $listener($event);
    }

    public function testHandleMatchedPathWithoutCsrfValidation()
    {
        $dispatcher = $this->getEventDispatcher();
        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($dispatcher);

        [$event, $request] = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $response = new Response();
        $dispatcher->addListener(LogoutEvent::class, function (LogoutEvent $event) use ($response) {
            $event->setResponse($response);
        });

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $listener($event);
    }

    public function testNoResponseSet()
    {
        $this->expectException('RuntimeException');

        [$listener, , $httpUtils, $options] = $this->getListener();

        [$event, $request] = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $listener($event);
    }

    public function testCsrfValidationFails()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\LogoutException');
        $tokenManager = $this->getTokenManager();

        [$listener, , $httpUtils, $options] = $this->getListener(null, $tokenManager);

        [$event, $request] = $this->getGetResponseEvent();

        $request->query->set('_csrf_token', 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(false);

        $listener($event);
    }

    /**
     * @group legacy
     */
    public function testLegacyLogoutHandlers()
    {
        $this->expectDeprecation('Since symfony/security-http 5.1: The "%s\LogoutSuccessHandlerInterface" interface is deprecated, create a listener for the "%s" event instead.');
        $this->expectDeprecation('Since symfony/security-http 5.1: Passing a logout success handler to "%s\LogoutListener::__construct" is deprecated, pass an instance of "%s" instead.');
        $this->expectDeprecation('Since symfony/security-http 5.1: Calling "%s::addHandler" is deprecated, register a listener on the "%s" event instead.');

        $logoutSuccessHandler = $this->createMock(LogoutSuccessHandlerInterface::class);
        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($logoutSuccessHandler);

        $token = $this->getToken();
        $tokenStorage->expects($this->any())->method('getToken')->willReturn($token);

        [$event, $request] = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $response = new Response();
        $logoutSuccessHandler->expects($this->any())->method('onLogoutSuccess')->willReturn($response);

        $handler = $this->createMock('Symfony\Component\Security\Http\Logout\LogoutHandlerInterface');
        $handler->expects($this->once())->method('logout')->with($request, $response, $token);
        $listener->addHandler($handler);

        $event->expects($this->once())->method('setResponse')->with($this->identicalTo($response));

        $listener($event);
    }

    private function getTokenManager()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock();
    }

    private function getTokenStorage()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
    }

    private function getGetResponseEvent()
    {
        $event = $this->getMockBuilder(RequestEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->willReturn($request = new Request());

        return [$event, $request];
    }

    private function getHttpUtils()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\HttpUtils')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getListener($eventDispatcher = null, $tokenManager = null)
    {
        $listener = new LogoutListener(
            $tokenStorage = $this->getTokenStorage(),
            $httpUtils = $this->getHttpUtils(),
            $eventDispatcher ?? $this->getEventDispatcher(),
            $options = [
                'csrf_parameter' => '_csrf_token',
                'csrf_token_id' => 'logout',
                'logout_path' => '/logout',
                'target_url' => '/',
            ],
            $tokenManager
        );

        return [$listener, $tokenStorage, $httpUtils, $options];
    }

    private function getEventDispatcher()
    {
        return new EventDispatcher();
    }

    private function getToken()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
    }
}

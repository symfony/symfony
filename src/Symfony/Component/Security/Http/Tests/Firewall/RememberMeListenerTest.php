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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Firewall\RememberMeListener;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RememberMeListenerTest extends TestCase
{
    public function testOnCoreSecurityDoesNotTryToPopulateNonEmptyTokenStorage()
    {
        [$listener, $tokenStorage] = $this->getListener();

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($this->createMock(TokenInterface::class))
        ;

        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $this->assertNull($listener($this->getRequestEvent()));
    }

    public function testOnCoreSecurityDoesNothingWhenNoCookieIsSet()
    {
        [$listener, $tokenStorage, $service] = $this->getListener();

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willReturn(null)
        ;

        $event = $this->getRequestEvent();

        $this->assertNull($listener($event));
    }

    public function testOnCoreSecurityIgnoresAuthenticationExceptionThrownByAuthenticationManagerImplementation()
    {
        [$listener, $tokenStorage, $service, $manager] = $this->getListener();
        $request = new Request();
        $exception = new AuthenticationException('Authentication failed.');

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willReturn($this->createMock(TokenInterface::class))
        ;

        $service
            ->expects($this->once())
            ->method('loginFail')
            ->with($request, $exception)
        ;

        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->willThrowException($exception)
        ;

        $event = $this->getRequestEvent($request);

        $listener($event);
    }

    public function testOnCoreSecurityIgnoresAuthenticationOptionallyRethrowsExceptionThrownAuthenticationManagerImplementation()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed.');
        [$listener, $tokenStorage, $service, $manager] = $this->getListener(false, false);

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willReturn($this->createMock(TokenInterface::class))
        ;

        $service
            ->expects($this->once())
            ->method('loginFail')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->willThrowException($exception)
        ;

        $event = $this->getRequestEvent();

        $listener($event);
    }

    public function testOnCoreSecurityAuthenticationExceptionDuringAutoLoginTriggersLoginFail()
    {
        [$listener, $tokenStorage, $service, $manager] = $this->getListener();

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willThrowException($exception)
        ;

        $service
            ->expects($this->once())
            ->method('loginFail')
        ;

        $manager
            ->expects($this->never())
            ->method('authenticate')
        ;

        $event = $this->getRequestEvent();

        $listener($event);
    }

    public function testOnCoreSecurity()
    {
        [$listener, $tokenStorage, $service, $manager] = $this->getListener();

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $token = $this->createMock(TokenInterface::class);
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willReturn($token)
        ;

        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($token))
        ;

        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->willReturn($token)
        ;

        $event = $this->getRequestEvent();

        $listener($event);
    }

    public function testSessionStrategy()
    {
        [$listener, $tokenStorage, $service, $manager, , , $sessionStrategy] = $this->getListener(false, true, true);

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $token = $this->createMock(TokenInterface::class);
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willReturn($token)
        ;

        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($token))
        ;

        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->willReturn($token)
        ;

        $session = $this->createMock(SessionInterface::class);
        $session
            ->expects($this->once())
            ->method('isStarted')
            ->willReturn(true)
        ;

        $request = new Request();
        $request->setSession($session);

        $event = $this->getRequestEvent($request);

        $sessionStrategy
            ->expects($this->once())
            ->method('onAuthentication')
            ->willReturn(null)
        ;

        $listener($event);
    }

    public function testSessionIsMigratedByDefault()
    {
        [$listener, $tokenStorage, $service, $manager] = $this->getListener(false, true, false);

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $token = $this->createMock(TokenInterface::class);
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willReturn($token)
        ;

        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($token))
        ;

        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->willReturn($token)
        ;

        $session = $this->createMock(SessionInterface::class);
        $session
            ->expects($this->once())
            ->method('isStarted')
            ->willReturn(true)
        ;
        $session
            ->expects($this->once())
            ->method('migrate')
        ;

        $request = new Request();
        $request->setSession($session);

        $event = $this->getRequestEvent($request);

        $listener($event);
    }

    public function testOnCoreSecurityInteractiveLoginEventIsDispatchedIfDispatcherIsPresent()
    {
        [$listener, $tokenStorage, $service, $manager, , $dispatcher] = $this->getListener(true);

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $token = $this->createMock(TokenInterface::class);
        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willReturn($token)
        ;

        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($token))
        ;

        $manager
            ->expects($this->once())
            ->method('authenticate')
            ->willReturn($token)
        ;

        $event = $this->getRequestEvent();

        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(InteractiveLoginEvent::class),
                SecurityEvents::INTERACTIVE_LOGIN
            )
        ;

        $listener($event);
    }

    protected function getRequestEvent(Request $request = null): RequestEvent
    {
        $request = $request ?? new Request();

        $event = $this->getMockBuilder(RequestEvent::class)
            ->setConstructorArgs([$this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST])
            ->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        return $event;
    }

    protected function getResponseEvent(): ResponseEvent
    {
        return $this->createMock(ResponseEvent::class);
    }

    protected function getListener($withDispatcher = false, $catchExceptions = true, $withSessionStrategy = false)
    {
        $listener = new RememberMeListener(
            $tokenStorage = $this->getTokenStorage(),
            $service = $this->getService(),
            $manager = $this->getManager(),
            $logger = $this->getLogger(),
            $dispatcher = ($withDispatcher ? $this->getDispatcher() : null),
            $catchExceptions,
            $sessionStrategy = ($withSessionStrategy ? $this->getSessionStrategy() : null)
        );

        return [$listener, $tokenStorage, $service, $manager, $logger, $dispatcher, $sessionStrategy];
    }

    protected function getLogger()
    {
        return $this->createMock(LoggerInterface::class);
    }

    protected function getManager()
    {
        return $this->createMock(AuthenticationManagerInterface::class);
    }

    protected function getService()
    {
        return $this->createMock(RememberMeServicesInterface::class);
    }

    protected function getTokenStorage()
    {
        return $this->createMock(TokenStorageInterface::class);
    }

    protected function getDispatcher()
    {
        return $this->createMock(EventDispatcherInterface::class);
    }

    private function getSessionStrategy()
    {
        return $this->createMock(SessionAuthenticationStrategyInterface::class);
    }
}

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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\RememberMeListener;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RememberMeListenerTest extends TestCase
{
    public function testOnCoreSecurityDoesNotTryToPopulateNonEmptyTokenStorage()
    {
        [$listener, $tokenStorage] = $this->getListener();

        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock())
        ;

        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $this->assertNull($listener($this->getGetResponseEvent()));
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

        $event = $this->getGetResponseEvent();

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
            ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock())
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

        $event = $this->getGetResponseEvent($request);

        $listener($event);
    }

    public function testOnCoreSecurityIgnoresAuthenticationOptionallyRethrowsExceptionThrownAuthenticationManagerImplementation()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AuthenticationException');
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
            ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock())
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

        $event = $this->getGetResponseEvent();

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

        $event = $this->getGetResponseEvent();

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

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
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

        $event = $this->getGetResponseEvent();

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

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
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

        $session = $this->getMockBuilder('\Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock();
        $session
            ->expects($this->once())
            ->method('isStarted')
            ->willReturn(true)
        ;

        $request = new Request();
        $request->setSession($session);

        $event = $this->getGetResponseEvent($request);

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

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
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

        $session = $this->getMockBuilder('\Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock();
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

        $event = $this->getGetResponseEvent($request);

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

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
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

        $event = $this->getGetResponseEvent();

        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf('Symfony\Component\Security\Http\Event\InteractiveLoginEvent'),
                SecurityEvents::INTERACTIVE_LOGIN
            )
        ;

        $listener($event);
    }

    protected function getGetResponseEvent(Request $request = null): RequestEvent
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
        return $this->getMockBuilder(ResponseEvent::class)->disableOriginalConstructor()->getMock();
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
        return $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
    }

    protected function getManager()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
    }

    protected function getService()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface')->getMock();
    }

    protected function getTokenStorage()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
    }

    protected function getDispatcher()
    {
        return $this->getMockBuilder(EventDispatcherInterface::class)->getMock();
    }

    private function getSessionStrategy()
    {
        return $this->getMockBuilder('\Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface')->getMock();
    }
}

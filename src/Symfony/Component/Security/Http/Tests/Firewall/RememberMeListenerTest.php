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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\RememberMeListener;
use Symfony\Component\Security\Http\SecurityEvents;

class RememberMeListenerTest extends TestCase
{
    public function testOnCoreSecurityDoesNotTryToPopulateNonEmptyTokenStorage()
    {
        list($listener, $tokenStorage) = $this->getListener();

        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock())
        ;

        $tokenStorage
            ->expects($this->never())
            ->method('setToken')
        ;

        $this->assertNull($listener->handle($this->getGetResponseEvent()));
    }

    public function testOnCoreSecurityDoesNothingWhenNoCookieIsSet()
    {
        list($listener, $tokenStorage, $service) = $this->getListener();

        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->willReturn(null)
        ;

        $service
            ->expects($this->once())
            ->method('autoLogin')
            ->willReturn(null)
        ;

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn(new Request())
        ;

        $this->assertNull($listener->handle($event));
    }

    public function testOnCoreSecurityIgnoresAuthenticationExceptionThrownByAuthenticationManagerImplementation()
    {
        list($listener, $tokenStorage, $service, $manager) = $this->getListener();
        $request = new Request();
        $exception = new AuthenticationException('Authentication failed.');

        $tokenStorage
            ->expects($this->once())
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

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testOnCoreSecurityIgnoresAuthenticationOptionallyRethrowsExceptionThrownAuthenticationManagerImplementation()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AuthenticationException');
        $this->expectExceptionMessage('Authentication failed.');
        list($listener, $tokenStorage, $service, $manager) = $this->getListener(false, false);

        $tokenStorage
            ->expects($this->once())
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
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn(new Request())
        ;

        $listener->handle($event);
    }

    public function testOnCoreSecurityAuthenticationExceptionDuringAutoLoginTriggersLoginFail()
    {
        list($listener, $tokenStorage, $service, $manager) = $this->getListener();

        $tokenStorage
            ->expects($this->once())
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
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn(new Request())
        ;

        $listener->handle($event);
    }

    public function testOnCoreSecurity()
    {
        list($listener, $tokenStorage, $service, $manager) = $this->getListener();

        $tokenStorage
            ->expects($this->once())
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
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn(new Request())
        ;

        $listener->handle($event);
    }

    public function testSessionStrategy()
    {
        list($listener, $tokenStorage, $service, $manager, , , $sessionStrategy) = $this->getListener(false, true, true);

        $tokenStorage
            ->expects($this->once())
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

        $request = $this->getMockBuilder('\Symfony\Component\HttpFoundation\Request')->getMock();
        $request
            ->expects($this->once())
            ->method('hasSession')
            ->willReturn(true)
        ;

        $request
            ->expects($this->once())
            ->method('getSession')
            ->willReturn($session)
        ;

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $sessionStrategy
            ->expects($this->once())
            ->method('onAuthentication')
            ->willReturn(null)
        ;

        $listener->handle($event);
    }

    public function testSessionIsMigratedByDefault()
    {
        list($listener, $tokenStorage, $service, $manager) = $this->getListener(false, true, false);

        $tokenStorage
            ->expects($this->once())
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

        $request = $this->getMockBuilder('\Symfony\Component\HttpFoundation\Request')->getMock();
        $request
            ->expects($this->any())
            ->method('hasSession')
            ->willReturn(true)
        ;

        $request
            ->expects($this->any())
            ->method('getSession')
            ->willReturn($session)
        ;

        $event = $this->getGetResponseEvent();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testOnCoreSecurityInteractiveLoginEventIsDispatchedIfDispatcherIsPresent()
    {
        list($listener, $tokenStorage, $service, $manager, , $dispatcher) = $this->getListener(true);

        $tokenStorage
            ->expects($this->once())
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
        $request = new Request();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                SecurityEvents::INTERACTIVE_LOGIN,
                $this->isInstanceOf('Symfony\Component\Security\Http\Event\InteractiveLoginEvent')
            )
        ;

        $listener->handle($event);
    }

    protected function getGetResponseEvent()
    {
        return $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
    }

    protected function getFilterResponseEvent()
    {
        return $this->getMockBuilder('Symfony\Component\HttpKernel\Event\FilterResponseEvent')->disableOriginalConstructor()->getMock();
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
        return $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
    }

    private function getSessionStrategy()
    {
        return $this->getMockBuilder('\Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface')->getMock();
    }
}

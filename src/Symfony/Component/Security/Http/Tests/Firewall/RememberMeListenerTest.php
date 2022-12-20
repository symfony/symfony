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

/**
 * @group legacy
 */
class RememberMeListenerTest extends TestCase
{
    public function testOnCoreSecurityDoesNotTryToPopulateNonEmptyTokenStorage()
    {
        [$listener, $tokenStorage] = $this->getListener();

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(self::createMock(TokenInterface::class))
        ;

        $tokenStorage
            ->expects(self::never())
            ->method('setToken')
        ;

        self::assertNull($listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST)));
    }

    public function testOnCoreSecurityDoesNothingWhenNoCookieIsSet()
    {
        [$listener, $tokenStorage, $service] = $this->getListener();

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $service
            ->expects(self::once())
            ->method('autoLogin')
            ->willReturn(null)
        ;

        self::assertNull($listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST)));
    }

    public function testOnCoreSecurityIgnoresAuthenticationExceptionThrownByAuthenticationManagerImplementation()
    {
        [$listener, $tokenStorage, $service, $manager] = $this->getListener();
        $request = new Request();
        $exception = new AuthenticationException('Authentication failed.');

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $service
            ->expects(self::once())
            ->method('autoLogin')
            ->willReturn(self::createMock(TokenInterface::class))
        ;

        $service
            ->expects(self::once())
            ->method('loginFail')
            ->with($request, $exception)
        ;

        $manager
            ->expects(self::once())
            ->method('authenticate')
            ->willThrowException($exception)
        ;

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testOnCoreSecurityIgnoresAuthenticationOptionallyRethrowsExceptionThrownAuthenticationManagerImplementation()
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('Authentication failed.');
        [$listener, $tokenStorage, $service, $manager] = $this->getListener(false, false);

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $service
            ->expects(self::once())
            ->method('autoLogin')
            ->willReturn(self::createMock(TokenInterface::class))
        ;

        $service
            ->expects(self::once())
            ->method('loginFail')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $manager
            ->expects(self::once())
            ->method('authenticate')
            ->willThrowException($exception)
        ;

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
    }

    public function testOnCoreSecurityAuthenticationExceptionDuringAutoLoginTriggersLoginFail()
    {
        [$listener, $tokenStorage, $service, $manager] = $this->getListener();

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $service
            ->expects(self::once())
            ->method('autoLogin')
            ->willThrowException($exception)
        ;

        $service
            ->expects(self::once())
            ->method('loginFail')
        ;

        $manager
            ->expects(self::never())
            ->method('authenticate')
        ;

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
    }

    public function testOnCoreSecurity()
    {
        [$listener, $tokenStorage, $service, $manager] = $this->getListener();

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $token = self::createMock(TokenInterface::class);
        $service
            ->expects(self::once())
            ->method('autoLogin')
            ->willReturn($token)
        ;

        $tokenStorage
            ->expects(self::once())
            ->method('setToken')
            ->with(self::equalTo($token))
        ;

        $manager
            ->expects(self::once())
            ->method('authenticate')
            ->willReturn($token)
        ;

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
    }

    public function testSessionStrategy()
    {
        [$listener, $tokenStorage, $service, $manager, , , $sessionStrategy] = $this->getListener(false, true, true);

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $token = self::createMock(TokenInterface::class);
        $service
            ->expects(self::once())
            ->method('autoLogin')
            ->willReturn($token)
        ;

        $tokenStorage
            ->expects(self::once())
            ->method('setToken')
            ->with(self::equalTo($token))
        ;

        $manager
            ->expects(self::once())
            ->method('authenticate')
            ->willReturn($token)
        ;

        $session = self::createMock(SessionInterface::class);
        $session
            ->expects(self::once())
            ->method('isStarted')
            ->willReturn(true)
        ;

        $request = new Request();
        $request->setSession($session);

        $sessionStrategy
            ->expects(self::once())
            ->method('onAuthentication')
            ->willReturn(null)
        ;

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testSessionIsMigratedByDefault()
    {
        [$listener, $tokenStorage, $service, $manager] = $this->getListener(false, true, false);

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $token = self::createMock(TokenInterface::class);
        $service
            ->expects(self::once())
            ->method('autoLogin')
            ->willReturn($token)
        ;

        $tokenStorage
            ->expects(self::once())
            ->method('setToken')
            ->with(self::equalTo($token))
        ;

        $manager
            ->expects(self::once())
            ->method('authenticate')
            ->willReturn($token)
        ;

        $session = self::createMock(SessionInterface::class);
        $session
            ->expects(self::once())
            ->method('isStarted')
            ->willReturn(true)
        ;
        $session
            ->expects(self::once())
            ->method('migrate')
        ;

        $request = new Request();
        $request->setSession($session);

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testOnCoreSecurityInteractiveLoginEventIsDispatchedIfDispatcherIsPresent()
    {
        [$listener, $tokenStorage, $service, $manager, , $dispatcher] = $this->getListener(true);

        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $token = self::createMock(TokenInterface::class);
        $service
            ->expects(self::once())
            ->method('autoLogin')
            ->willReturn($token)
        ;

        $tokenStorage
            ->expects(self::once())
            ->method('setToken')
            ->with(self::equalTo($token))
        ;

        $manager
            ->expects(self::once())
            ->method('authenticate')
            ->willReturn($token)
        ;

        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(InteractiveLoginEvent::class),
                SecurityEvents::INTERACTIVE_LOGIN
            )
        ;

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
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
        return self::createMock(LoggerInterface::class);
    }

    protected function getManager()
    {
        return self::createMock(AuthenticationManagerInterface::class);
    }

    protected function getService()
    {
        return self::createMock(RememberMeServicesInterface::class);
    }

    protected function getTokenStorage()
    {
        return self::createMock(TokenStorageInterface::class);
    }

    protected function getDispatcher()
    {
        return self::createMock(EventDispatcherInterface::class);
    }

    private function getSessionStrategy()
    {
        return self::createMock(SessionAuthenticationStrategyInterface::class);
    }
}

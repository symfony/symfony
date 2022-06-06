<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Firewall\GuardAuthenticationListener;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Guard\Token\PreAuthenticationGuardToken;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 * @group legacy
 */
class GuardAuthenticationListenerTest extends TestCase
{
    private $authenticationManager;
    private $guardAuthenticatorHandler;
    private $event;
    private $logger;
    private $request;
    private $rememberMeServices;

    public function testHandleSuccess()
    {
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticateToken = $this->createMock(TokenInterface::class);
        $providerKey = 'my_firewall';

        $credentials = ['username' => 'weaverryan', 'password' => 'all_your_base'];

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->with($this->equalTo($this->request))
            ->willReturn($credentials);

        // a clone of the token that should be created internally
        $uniqueGuardKey = 'my_firewall_0';
        $nonAuthedToken = new PreAuthenticationGuardToken($credentials, $uniqueGuardKey);

        $this->authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($nonAuthedToken))
            ->willReturn($authenticateToken);

        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('authenticateWithToken')
            ->with($authenticateToken, $this->request);

        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationSuccess')
            ->with($authenticateToken, $this->request, $authenticator, $providerKey);

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            [$authenticator],
            $this->logger
        );

        $listener->setRememberMeServices($this->rememberMeServices);
        // should never be called - our handleAuthenticationSuccess() does not return a Response
        $this->rememberMeServices
            ->expects($this->never())
            ->method('loginSuccess');

        $listener($this->event);
    }

    public function testHandleSuccessStopsAfterResponseIsSet()
    {
        $authenticator1 = $this->createMock(AuthenticatorInterface::class);
        $authenticator2 = $this->createMock(AuthenticatorInterface::class);

        // mock the first authenticator to fail, and set a Response
        $authenticator1
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $authenticator1
            ->expects($this->once())
            ->method('getCredentials')
            ->willThrowException(new AuthenticationException());
        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationFailure')
            ->willReturn(new Response());
        // the second authenticator should *never* be called
        $authenticator2
            ->expects($this->never())
            ->method('getCredentials');

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            'my_firewall',
            [$authenticator1, $authenticator2],
            $this->logger
        );

        $listener($this->event);
    }

    public function testHandleSuccessWithRememberMe()
    {
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticateToken = $this->createMock(TokenInterface::class);
        $providerKey = 'my_firewall_with_rememberme';

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->with($this->equalTo($this->request))
            ->willReturn(true);
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->with($this->equalTo($this->request))
            ->willReturn(['username' => 'anything_not_empty']);

        $this->authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->willReturn($authenticateToken);

        $successResponse = new Response('Success!');
        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationSuccess')
            ->willReturn($successResponse);

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            [$authenticator],
            $this->logger
        );

        $listener->setRememberMeServices($this->rememberMeServices);
        $authenticator->expects($this->once())
            ->method('supportsRememberMe')
            ->willReturn(true);
        // should be called - we do have a success Response
        $this->rememberMeServices
            ->expects($this->once())
            ->method('loginSuccess');

        $listener($this->event);
    }

    public function testHandleCatchesAuthenticationException()
    {
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $providerKey = 'my_firewall2';

        $authException = new AuthenticationException('Get outta here crazy user with a bad password!');
        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->willThrowException($authException);

        // this is not called
        $this->authenticationManager
            ->expects($this->never())
            ->method('authenticate');

        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationFailure')
            ->with($authException, $this->request, $authenticator, $providerKey);

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            [$authenticator],
            $this->logger
        );

        $listener($this->event);
    }

    /**
     * @dataProvider exceptionsToHide
     */
    public function testHandleHidesInvalidUserExceptions(AuthenticationException $exceptionToHide)
    {
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $providerKey = 'my_firewall2';

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->willReturn(['username' => 'robin', 'password' => 'hood']);

        $this->authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->willThrowException($exceptionToHide);

        $this->guardAuthenticatorHandler
            ->expects($this->once())
            ->method('handleAuthenticationFailure')
            ->with($this->callback(function ($e) use ($exceptionToHide) {
                return $e instanceof BadCredentialsException && $exceptionToHide === $e->getPrevious();
            }), $this->request, $authenticator, $providerKey);

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            [$authenticator],
            $this->logger
        );

        $listener($this->event);
    }

    public function exceptionsToHide()
    {
        return [
            [new UserNotFoundException()],
            [new LockedException()],
        ];
    }

    public function testSupportsReturnFalseSkipAuth()
    {
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $providerKey = 'my_firewall4';

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->willReturn(false);

        // this is not called
        $authenticator
            ->expects($this->never())
            ->method('getCredentials');

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            [$authenticator],
            $this->logger
        );

        $listener($this->event);
    }

    public function testReturnNullFromGetCredentials()
    {
        $this->expectException(\UnexpectedValueException::class);
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $providerKey = 'my_firewall4';

        $authenticator
            ->expects($this->once())
            ->method('supports')
            ->willReturn(true);

        // this will raise exception
        $authenticator
            ->expects($this->once())
            ->method('getCredentials')
            ->willReturn(null);

        $listener = new GuardAuthenticationListener(
            $this->guardAuthenticatorHandler,
            $this->authenticationManager,
            $providerKey,
            [$authenticator],
            $this->logger
        );

        $listener($this->event);
    }

    protected function setUp(): void
    {
        $this->authenticationManager = $this->createMock(AuthenticationProviderManager::class);
        $this->guardAuthenticatorHandler = $this->createMock(GuardAuthenticatorHandler::class);
        $this->request = new Request([], [], [], [], [], []);

        $this->event = new RequestEvent($this->createMock(HttpKernelInterface::class), $this->request, HttpKernelInterface::MAIN_REQUEST);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->rememberMeServices = $this->createMock(RememberMeServicesInterface::class);
    }

    protected function tearDown(): void
    {
        $this->authenticationManager = null;
        $this->guardAuthenticatorHandler = null;
        $this->event = null;
        $this->logger = null;
        $this->request = null;
        $this->rememberMeServices = null;
    }
}

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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\AbstractPreAuthenticatedListener;

/**
 * @group legacy
 */
class AbstractPreAuthenticatedListenerTest extends TestCase
{
    public function testHandleWithValidValues()
    {
        $userCredentials = ['TheUser', 'TheCredentials'];

        $request = new Request([], [], [], [], [], []);

        $token = self::createMock(TokenInterface::class);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;
        $tokenStorage
            ->expects(self::once())
            ->method('setToken')
            ->with(self::equalTo($token))
        ;

        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);
        $authenticationManager
            ->expects(self::once())
            ->method('authenticate')
            ->with(self::isInstanceOf(PreAuthenticatedToken::class))
            ->willReturn($token)
        ;

        $listener = self::getMockForAbstractClass(AbstractPreAuthenticatedListener::class, [
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
        ]);
        $listener
            ->expects(self::once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenAuthenticationFails()
    {
        $userCredentials = ['TheUser', 'TheCredentials'];

        $request = new Request([], [], [], [], [], []);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;
        $tokenStorage
            ->expects(self::never())
            ->method('setToken')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);
        $authenticationManager
            ->expects(self::once())
            ->method('authenticate')
            ->with(self::isInstanceOf(PreAuthenticatedToken::class))
            ->willThrowException($exception)
        ;

        $listener = self::getMockForAbstractClass(AbstractPreAuthenticatedListener::class, [
        $tokenStorage,
        $authenticationManager,
        'TheProviderKey',
    ]);
        $listener
            ->expects(self::once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenAuthenticationFailsWithDifferentToken()
    {
        $userCredentials = ['TheUser', 'TheCredentials'];

        $token = new UsernamePasswordToken('TheUsername', 'ThePassword', 'TheProviderKey', ['ROLE_FOO']);

        $request = new Request([], [], [], [], [], []);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn($token)
        ;
        $tokenStorage
            ->expects(self::never())
            ->method('setToken')
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);
        $authenticationManager
            ->expects(self::once())
            ->method('authenticate')
            ->with(self::isInstanceOf(PreAuthenticatedToken::class))
            ->willThrowException($exception)
        ;

        $listener = self::getMockForAbstractClass(AbstractPreAuthenticatedListener::class, [
        $tokenStorage,
        $authenticationManager,
        'TheProviderKey',
    ]);
        $listener
            ->expects(self::once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener($event);
    }

    public function testHandleWithASimilarAuthenticatedToken()
    {
        $userCredentials = ['TheUser', 'TheCredentials'];

        $request = new Request([], [], [], [], [], []);

        $token = new PreAuthenticatedToken('TheUser', 'TheCredentials', 'TheProviderKey', ['ROLE_FOO']);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);
        $authenticationManager
            ->expects(self::never())
            ->method('authenticate')
        ;

        $listener = self::getMockForAbstractClass(AbstractPreAuthenticatedListener::class, [
        $tokenStorage,
        $authenticationManager,
        'TheProviderKey',
    ]);
        $listener
            ->expects(self::once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWithAnInvalidSimilarToken()
    {
        $userCredentials = ['TheUser', 'TheCredentials'];

        $request = new Request([], [], [], [], [], []);

        $token = new PreAuthenticatedToken('AnotherUser', 'TheCredentials', 'TheProviderKey', ['ROLE_FOO']);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn($token)
        ;
        $tokenStorage
            ->expects(self::once())
            ->method('setToken')
            ->with(self::equalTo(null))
        ;

        $exception = new AuthenticationException('Authentication failed.');
        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);
        $authenticationManager
            ->expects(self::once())
            ->method('authenticate')
            ->with(self::isInstanceOf(PreAuthenticatedToken::class))
            ->willThrowException($exception)
        ;

        $listener = self::getMockForAbstractClass(AbstractPreAuthenticatedListener::class, [
        $tokenStorage,
        $authenticationManager,
        'TheProviderKey',
    ]);
        $listener
            ->expects(self::once())
            ->method('getPreAuthenticatedData')
            ->willReturn($userCredentials);

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }
}

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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\BasicAuthenticationListener;

/**
 * @group legacy
 */
class BasicAuthenticationListenerTest extends TestCase
{
    public function testHandleWithValidUsernameAndPasswordServerParameters()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

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
            ->with(self::isInstanceOf(UsernamePasswordToken::class))
            ->willReturn($token)
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
            self::createMock(AuthenticationEntryPointInterface::class)
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenAuthenticationFails()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

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

        $response = new Response();

        $authenticationEntryPoint = self::createMock(AuthenticationEntryPointInterface::class);
        $authenticationEntryPoint
            ->expects(self::any())
            ->method('start')
            ->with(self::equalTo($request), self::isInstanceOf(AuthenticationException::class))
            ->willReturn($response)
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            new AuthenticationProviderManager([self::createMock(AuthenticationProviderInterface::class)]),
            'TheProviderKey',
            $authenticationEntryPoint
        );

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener($event);

        self::assertSame($response, $event->getResponse());
    }

    public function testHandleWithNoUsernameServerParameter()
    {
        $request = new Request();

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::never())
            ->method('getToken')
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            self::createMock(AuthenticationManagerInterface::class),
            'TheProviderKey',
            self::createMock(AuthenticationEntryPointInterface::class)
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWithASimilarAuthenticatedToken()
    {
        $request = new Request([], [], [], [], [], ['PHP_AUTH_USER' => 'TheUsername']);

        $token = new UsernamePasswordToken('TheUsername', 'ThePassword', 'TheProviderKey', ['ROLE_FOO']);

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

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            $authenticationManager,
            'TheProviderKey',
            self::createMock(AuthenticationEntryPointInterface::class)
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testItRequiresProviderKey()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('$providerKey must not be empty');
        new BasicAuthenticationListener(
            self::createMock(TokenStorageInterface::class),
            self::createMock(AuthenticationManagerInterface::class),
            '',
            self::createMock(AuthenticationEntryPointInterface::class)
        );
    }

    public function testHandleWithADifferentAuthenticatedToken()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $token = new PreAuthenticatedToken('TheUser', 'TheCredentials', 'TheProviderKey', ['ROLE_FOO']);

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

        $response = new Response();

        $authenticationEntryPoint = self::createMock(AuthenticationEntryPointInterface::class);
        $authenticationEntryPoint
            ->expects(self::any())
            ->method('start')
            ->with(self::equalTo($request), self::isInstanceOf(AuthenticationException::class))
            ->willReturn($response)
        ;

        $listener = new BasicAuthenticationListener(
            $tokenStorage,
            new AuthenticationProviderManager([self::createMock(AuthenticationProviderInterface::class)]),
            'TheProviderKey',
            $authenticationEntryPoint
        );

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener($event);

        self::assertSame($response, $event->getResponse());
    }
}

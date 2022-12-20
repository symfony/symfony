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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Firewall\AnonymousAuthenticationListener;

/**
 * @group legacy
 */
class AnonymousAuthenticationListenerTest extends TestCase
{
    public function testHandleWithTokenStorageHavingAToken()
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(self::createMock(TokenInterface::class))
        ;
        $tokenStorage
            ->expects(self::never())
            ->method('setToken')
        ;

        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);
        $authenticationManager
            ->expects(self::never())
            ->method('authenticate')
        ;

        $listener = new AnonymousAuthenticationListener($tokenStorage, 'TheSecret', null, $authenticationManager);
        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWithTokenStorageHavingNoToken()
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $anonymousToken = new AnonymousToken('TheSecret', 'anon.', []);

        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);
        $authenticationManager
            ->expects(self::once())
            ->method('authenticate')
            ->with(self::callback(function ($token) {
                return 'TheSecret' === $token->getSecret();
            }))
            ->willReturn($anonymousToken)
        ;

        $tokenStorage
            ->expects(self::once())
            ->method('setToken')
            ->with($anonymousToken)
        ;

        $listener = new AnonymousAuthenticationListener($tokenStorage, 'TheSecret', null, $authenticationManager);
        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandledEventIsLogged()
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Populated the TokenStorage with an anonymous Token.')
        ;

        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);

        $listener = new AnonymousAuthenticationListener($tokenStorage, 'TheSecret', $logger, $authenticationManager);
        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
    }
}

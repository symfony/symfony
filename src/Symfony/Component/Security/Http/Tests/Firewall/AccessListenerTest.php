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
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;
use Symfony\Component\Security\Http\Firewall\AccessListener;

class AccessListenerTest extends TestCase
{
    public function testHandleWhenTheAccessDecisionManagerDecidesToRefuseAccess()
    {
        self::expectException(AccessDeniedException::class);
        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $token = new class() extends AbstractToken {
            public function isAuthenticated(): bool
            {
                return true;
            }

            /**
             * @return mixed
             */
            public function getCredentials()
            {
            }
        };

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $accessDecisionManager = self::createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->expects(self::once())
            ->method('decide')
            ->with(self::equalTo($token), self::equalTo(['foo' => 'bar']), self::equalTo($request))
            ->willReturn(false)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @group legacy
     */
    public function testHandleWhenTheTokenIsNotAuthenticated()
    {
        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $notAuthenticatedToken = self::createMock(TokenInterface::class);
        $notAuthenticatedToken
            ->expects(self::any())
            ->method('isAuthenticated')
            ->willReturn(false)
        ;

        $authenticatedToken = self::createMock(TokenInterface::class);
        $authenticatedToken
            ->expects(self::any())
            ->method('isAuthenticated')
            ->willReturn(true)
        ;

        $authManager = self::createMock(AuthenticationManagerInterface::class);
        $authManager
            ->expects(self::once())
            ->method('authenticate')
            ->with(self::equalTo($notAuthenticatedToken))
            ->willReturn($authenticatedToken)
        ;

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn($notAuthenticatedToken)
        ;
        $tokenStorage
            ->expects(self::once())
            ->method('setToken')
            ->with(self::equalTo($authenticatedToken))
        ;

        $accessDecisionManager = self::createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->expects(self::once())
            ->method('decide')
            ->with(self::equalTo($authenticatedToken), self::equalTo(['foo' => 'bar']), self::equalTo($request))
            ->willReturn(true)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            $authManager,
            false
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenThereIsNoAccessMapEntryMatchingTheRequest()
    {
        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([null, null])
        ;

        $token = self::createMock(TokenInterface::class);
        if (method_exists(TokenInterface::class, 'isAuthenticated')) {
            $token
                ->expects(self::never())
                ->method('isAuthenticated')
            ;
        }

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            self::createMock(AccessDecisionManagerInterface::class),
            $accessMap,
            false
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenAccessMapReturnsEmptyAttributes()
    {
        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[], null])
        ;

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::never())
            ->method('getToken')
        ;

        $listener = new AccessListener(
            $tokenStorage,
            self::createMock(AccessDecisionManagerInterface::class),
            $accessMap,
            false
        );

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener(new LazyResponseEvent($event));
    }

    /**
     * @group legacy
     */
    public function testLegacyHandleWhenTheSecurityTokenStorageHasNoToken()
    {
        self::expectException(AuthenticationCredentialsNotFoundException::class);
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects(self::any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $listener = new AccessListener(
            $tokenStorage,
            self::createMock(AccessDecisionManagerInterface::class),
            $accessMap,
            self::createMock(AuthenticationManagerInterface::class)
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenTheSecurityTokenStorageHasNoToken()
    {
        self::expectException(AccessDeniedException::class);
        $tokenStorage = new TokenStorage();
        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $accessDecisionManager = self::createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects(self::once())
            ->method('decide')
            ->with(self::isInstanceOf(NullToken::class))
            ->willReturn(false);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenPublicAccessIsAllowed()
    {
        $tokenStorage = new TokenStorage();
        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[AuthenticatedVoter::PUBLIC_ACCESS], null])
        ;

        $accessDecisionManager = self::createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects(self::once())
            ->method('decide')
            ->with(self::isInstanceOf(NullToken::class), [AuthenticatedVoter::PUBLIC_ACCESS])
            ->willReturn(true);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenPublicAccessWhileAuthenticated()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('Wouter', null, ['ROLE_USER']), 'main', ['ROLE_USER']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);
        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[AuthenticatedVoter::PUBLIC_ACCESS], null])
        ;

        $accessDecisionManager = self::createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects(self::once())
            ->method('decide')
            ->with(self::equalTo($token), [AuthenticatedVoter::PUBLIC_ACCESS])
            ->willReturn(true);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleMWithultipleAttributesShouldBeHandledAsAnd()
    {
        $request = new Request();

        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap
            ->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([['foo' => 'bar', 'bar' => 'baz'], null])
        ;

        $authenticatedToken = new UsernamePasswordToken(new InMemoryUser('test', 'test', ['ROLE_USER']), 'test', ['ROLE_USER']);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($authenticatedToken);

        $accessDecisionManager = self::createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->expects(self::once())
            ->method('decide')
            ->with(self::equalTo($authenticatedToken), self::equalTo(['foo' => 'bar', 'bar' => 'baz']), self::equalTo($request), true)
            ->willReturn(true)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testLazyPublicPagesShouldNotAccessTokenStorage()
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::never())->method('getToken');

        $request = new Request();
        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[AuthenticatedVoter::PUBLIC_ACCESS], null])
        ;

        $listener = new AccessListener($tokenStorage, self::createMock(AccessDecisionManagerInterface::class), $accessMap, false);
        $listener(new LazyResponseEvent(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST)));
    }

    /**
     * @group legacy
     */
    public function testLegacyLazyPublicPagesShouldNotAccessTokenStorage()
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::never())->method('getToken');

        $request = new Request();
        $accessMap = self::createMock(AccessMapInterface::class);
        $accessMap->expects(self::any())
            ->method('getPatterns')
            ->with(self::equalTo($request))
            ->willReturn([[AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY], null])
        ;

        $listener = new AccessListener($tokenStorage, self::createMock(AccessDecisionManagerInterface::class), $accessMap, false);
        $listener(new LazyResponseEvent(new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST)));
    }
}

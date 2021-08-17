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
        $this->expectException(AccessDeniedException::class);
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
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

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->equalTo($token), $this->equalTo(['foo' => 'bar']), $this->equalTo($request))
            ->willReturn(false)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @group legacy
     */
    public function testHandleWhenTheTokenIsNotAuthenticated()
    {
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $notAuthenticatedToken = $this->createMock(TokenInterface::class);
        $notAuthenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(false)
        ;

        $authenticatedToken = $this->createMock(TokenInterface::class);
        $authenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(true)
        ;

        $authManager = $this->createMock(AuthenticationManagerInterface::class);
        $authManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($notAuthenticatedToken))
            ->willReturn($authenticatedToken)
        ;

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($notAuthenticatedToken)
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($authenticatedToken))
        ;

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->equalTo($authenticatedToken), $this->equalTo(['foo' => 'bar']), $this->equalTo($request))
            ->willReturn(true)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            $authManager,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenThereIsNoAccessMapEntryMatchingTheRequest()
    {
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([null, null])
        ;

        $token = $this->createMock(TokenInterface::class);
        if (method_exists(TokenInterface::class, 'isAuthenticated')) {
            $token
                ->expects($this->never())
                ->method('isAuthenticated')
            ;
        }

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->createMock(AccessDecisionManagerInterface::class),
            $accessMap,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenAccessMapReturnsEmptyAttributes()
    {
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[], null])
        ;

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->never())
            ->method('getToken')
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->createMock(AccessDecisionManagerInterface::class),
            $accessMap,
            false
        );

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener(new LazyResponseEvent($event));
    }

    /**
     * @group legacy
     */
    public function testLegacyHandleWhenTheSecurityTokenStorageHasNoToken()
    {
        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->createMock(AccessDecisionManagerInterface::class),
            $accessMap,
            $this->createMock(AuthenticationManagerInterface::class)
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenTheSecurityTokenStorageHasNoToken()
    {
        $this->expectException(AccessDeniedException::class);
        $tokenStorage = new TokenStorage();
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')
            ->with($this->isInstanceOf(NullToken::class))
            ->willReturn(false);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenPublicAccessIsAllowed()
    {
        $tokenStorage = new TokenStorage();
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[AuthenticatedVoter::PUBLIC_ACCESS], null])
        ;

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')
            ->with($this->isInstanceOf(NullToken::class), [AuthenticatedVoter::PUBLIC_ACCESS])
            ->willReturn(true);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleWhenPublicAccessWhileAuthenticated()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('Wouter', null, ['ROLE_USER']), 'main', ['ROLE_USER']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[AuthenticatedVoter::PUBLIC_ACCESS], null])
        ;

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')
            ->with($this->equalTo($token), [AuthenticatedVoter::PUBLIC_ACCESS])
            ->willReturn(true);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testHandleMWithultipleAttributesShouldBeHandledAsAnd()
    {
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar', 'bar' => 'baz'], null])
        ;

        $authenticatedToken = new UsernamePasswordToken(new InMemoryUser('test', 'test', ['ROLE_USER']), 'test', ['ROLE_USER']);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($authenticatedToken);

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->equalTo($authenticatedToken), $this->equalTo(['foo' => 'bar', 'bar' => 'baz']), $this->equalTo($request), true)
            ->willReturn(true)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testLazyPublicPagesShouldNotAccessTokenStorage()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->never())->method('getToken');

        $request = new Request();
        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[AuthenticatedVoter::PUBLIC_ACCESS], null])
        ;

        $listener = new AccessListener($tokenStorage, $this->createMock(AccessDecisionManagerInterface::class), $accessMap, false);
        $listener(new LazyResponseEvent(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST)));
    }

    /**
     * @group legacy
     */
    public function testLegacyLazyPublicPagesShouldNotAccessTokenStorage()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->never())->method('getToken');

        $request = new Request();
        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY], null])
        ;

        $listener = new AccessListener($tokenStorage, $this->createMock(AccessDecisionManagerInterface::class), $accessMap, false);
        $listener(new LazyResponseEvent(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST)));
    }
}

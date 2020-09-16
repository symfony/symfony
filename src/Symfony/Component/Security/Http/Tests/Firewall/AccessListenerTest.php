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
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;
use Symfony\Component\Security\Http\Firewall\AccessListener;

class AccessListenerTest extends TestCase
{
    public function testHandleWhenTheAccessDecisionManagerDecidesToRefuseAccess()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AccessDeniedException');
        $request = new Request();

        $accessMap = $this->getMockBuilder('Symfony\Component\Security\Http\AccessMapInterface')->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(true)
        ;

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();
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
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock()
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function testHandleWhenTheTokenIsNotAuthenticated()
    {
        $request = new Request();

        $accessMap = $this->getMockBuilder('Symfony\Component\Security\Http\AccessMapInterface')->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $notAuthenticatedToken = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $notAuthenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(false)
        ;

        $authenticatedToken = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $authenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(true)
        ;

        $authManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($notAuthenticatedToken))
            ->willReturn($authenticatedToken)
        ;

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
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

        $accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();
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
            $authManager
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function testHandleWhenThereIsNoAccessMapEntryMatchingTheRequest()
    {
        $request = new Request();

        $accessMap = $this->getMockBuilder('Symfony\Component\Security\Http\AccessMapInterface')->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([null, null])
        ;

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token
            ->expects($this->never())
            ->method('isAuthenticated')
        ;

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock(),
            $accessMap,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock()
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function testHandleWhenAccessMapReturnsEmptyAttributes()
    {
        $request = new Request();

        $accessMap = $this->getMockBuilder(AccessMapInterface::class)->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[], null])
        ;

        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->never())
            ->method('getToken')
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->getMockBuilder(AccessDecisionManagerInterface::class)->getMock(),
            $accessMap,
            $this->getMockBuilder(AuthenticationManagerInterface::class)->getMock()
        );

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST);

        $listener(new LazyResponseEvent($event));
    }

    public function testHandleWhenTheSecurityTokenStorageHasNoToken()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException');
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $request = new Request();

        $accessMap = $this->getMockBuilder(AccessMapInterface::class)->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock(),
            $accessMap,
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock()
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function testHandleWhenTheSecurityTokenStorageHasNoTokenAndExceptionOnTokenIsFalse()
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
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function testHandleWhenPublicAccessIsAllowedAndExceptionOnTokenIsFalse()
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
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function testHandleWhenPublicAccessWhileAuthenticated()
    {
        $token = new UsernamePasswordToken(new User('Wouter', null, ['ROLE_USER']), null, 'main', ['ROLE_USER']);
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
            $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(),
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function testHandleMWithultipleAttributesShouldBeHandledAsAnd()
    {
        $request = new Request();

        $accessMap = $this->getMockBuilder('Symfony\Component\Security\Http\AccessMapInterface')->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar', 'bar' => 'baz'], null])
        ;

        $authenticatedToken = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $authenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(true)
        ;

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($authenticatedToken);

        $accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();
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
            $this->createMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MASTER_REQUEST));
    }
}

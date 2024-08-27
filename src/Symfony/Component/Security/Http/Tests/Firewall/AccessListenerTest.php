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
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;
use Symfony\Component\Security\Http\Firewall\AccessListener;

class AccessListenerTest extends TestCase
{
    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testHandleWhenTheAccessDecisionManagerDecidesToRefuseAccess(string $decideFunction, bool $useVoteObject)
    {
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $token = new class extends AbstractToken {
            public function getCredentials(): mixed
            {
            }
        };

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $accessDecisionManager = $this->getAccessManager($useVoteObject);
        $accessDecisionManager
            ->expects($this->once())
            ->method($decideFunction)
            ->with($this->equalTo($token), $this->equalTo(['foo' => 'bar']), $this->equalTo($request))
            ->willReturn($useVoteObject ? new AccessDecision(VoterInterface::ACCESS_DENIED) : false)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap
        );

        $this->expectException(AccessDeniedException::class);

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

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->never())
            ->method('getToken')
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->createMock(AccessDecisionManagerInterface::class),
            $accessMap
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
            $accessMap
        );

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener(new LazyResponseEvent($event));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testHandleWhenTheSecurityTokenStorageHasNoToken(string $decideFunction, bool $useVoteObject)
    {
        $tokenStorage = new TokenStorage();
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $accessDecisionManager = $this->getAccessManager($useVoteObject);
        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)
            ->with($this->isInstanceOf(NullToken::class))
            ->willReturn($useVoteObject ? new AccessDecision(VoterInterface::ACCESS_DENIED) : false);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $this->expectException(AccessDeniedException::class);

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testHandleWhenPublicAccessIsAllowed(string $decideFunction, bool $useVoteObject)
    {
        $tokenStorage = new TokenStorage();
        $request = new Request();

        $accessMap = $this->createMock(AccessMapInterface::class);
        $accessMap->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([[AuthenticatedVoter::PUBLIC_ACCESS], null])
        ;

        $accessDecisionManager = $this->getAccessManager($useVoteObject);
        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)
            ->with($this->isInstanceOf(NullToken::class), [AuthenticatedVoter::PUBLIC_ACCESS])
            ->willReturn($useVoteObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testHandleWhenPublicAccessWhileAuthenticated(string $decideFunction, bool $useVoteObject)
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

        $accessDecisionManager = $this->getAccessManager($useVoteObject);
        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)
            ->with($this->equalTo($token), [AuthenticatedVoter::PUBLIC_ACCESS])
            ->willReturn($useVoteObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            false
        );

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testHandleMWithultipleAttributesShouldBeHandledAsAnd(string $decideFunction, bool $useVoteObject)
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

        $accessDecisionManager = $this->getAccessManager($useVoteObject);
        $accessDecisionManager
            ->expects($this->once())
            ->method($decideFunction)
            ->with($this->equalTo($authenticatedToken), $this->equalTo(['foo' => 'bar', 'bar' => 'baz']), $this->equalTo($request), true)
            ->willReturn($useVoteObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap
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

    public function testConstructWithTrueExceptionOnNoToken()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->never())->method(self::anything());

        $accessMap = $this->createMock(AccessMapInterface::class);

        $this->expectExceptionObject(
            new \LogicException('Argument $exceptionOnNoToken of "Symfony\Component\Security\Http\Firewall\AccessListener::__construct()" must be set to "false".')
        );

        new AccessListener($tokenStorage, $this->createMock(AccessDecisionManagerInterface::class), $accessMap, true);
    }

    public function provideDataWithAndWithoutVoteObject()
    {
        yield [
            'decideFunction' => 'decide',
            'useVoteObject' => false,
        ];

        yield [
            'decideFunction' => 'getDecision',
            'useVoteObject' => true,
        ];
    }

    public function getAccessManager(bool $withObject)
    {
        return $withObject ?
            $this
                ->getMockBuilder(AccessDecisionManagerInterface::class)
                ->onlyMethods(['decide'])
                ->addMethods(['getDecision'])
                ->getMock() :
            $this->createMock(AccessDecisionManagerInterface::class);
    }
}

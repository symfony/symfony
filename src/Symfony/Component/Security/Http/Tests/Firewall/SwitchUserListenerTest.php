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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SwitchUserListenerTest extends TestCase
{
    private TokenStorage $tokenStorage;
    private InMemoryUserProvider $userProvider;
    private MockObject&UserCheckerInterface $userChecker;
    private MockObject&AccessDecisionManagerInterface $accessDecisionManager;
    private Request $request;
    private RequestEvent $event;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->userProvider = new InMemoryUserProvider(['kuba' => []]);
        $this->userChecker = $this->createMock(UserCheckerInterface::class);
        $this->accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $this->request = new Request();
        $this->event = new RequestEvent($this->createMock(HttpKernelInterface::class), $this->request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testFirewallNameIsRequired()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$firewallName must not be empty');
        new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, '', $this->accessDecisionManager);
    }

    public function testEventIsIgnoredIfUsernameIsNotPassedWithTheRequest()
    {
        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $listener($this->event);

        $this->assertNull($this->event->getResponse());
        $this->assertNull($this->tokenStorage->getToken());
    }

    public function testExitUserThrowsAuthenticationExceptionIfNoCurrentToken()
    {
        $this->tokenStorage->setToken(null);
        $this->request->query->set('_switch_user', '_exit');
        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);

        $listener($this->event);
    }

    public function testExitUserThrowsAuthenticationExceptionIfOriginalTokenCannotBeFound()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);

        $listener($this->event);
    }

    public function testExitUserUpdatesToken()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('username', '', []), 'key', []);
        $this->tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('username', '', ['ROLE_USER']), 'key', ['ROLE_USER'], $originalToken));

        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $listener($this->event);

        $this->assertSame([], $this->request->query->all());
        $this->assertSame('', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(RedirectResponse::class, $this->event->getResponse());
        $this->assertSame($this->request->getUri(), $this->event->getResponse()->getTargetUrl());
        $this->assertSame($originalToken, $this->tokenStorage->getToken());
    }

    public function testExitUserDispatchesEventWithRefreshedUser()
    {
        $originalUser = new InMemoryUser('username', null);
        $refreshedUser = new InMemoryUser('username', null);
        $userProvider = $this->createMock(InMemoryUserProvider::class);
        $userProvider
            ->expects($this->any())
            ->method('refreshUser')
            ->with($this->identicalTo($originalUser))
            ->willReturn($refreshedUser);
        $originalToken = new UsernamePasswordToken($originalUser, 'key');
        $this->tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('username', '', ['ROLE_USER']), 'key', ['ROLE_USER'], $originalToken));
        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(fn (SwitchUserEvent $event) => $event->getTargetUser() === $refreshedUser),
                SecurityEvents::SWITCH_USER
            )
        ;

        $listener = new SwitchUserListener($this->tokenStorage, $userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', $dispatcher);
        $listener($this->event);
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testSwitchUserIsDisallowed($accessDecisionManager, string $decideFunction, bool $returnAsObject)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $user = new InMemoryUser('username', 'password', []);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)->with($token, ['ROLE_ALLOWED_TO_SWITCH'])
            ->willReturn($returnAsObject ? new AccessDecision(VoterInterface::ACCESS_DENIED) : false);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager);

        $this->expectException(AccessDeniedException::class);

        $listener($this->event);
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testSwitchUserTurnsAuthenticationExceptionTo403($accessDecisionManager, string $decideFunction, bool $returnAsObject)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_ALLOWED_TO_SWITCH']), 'key', ['ROLE_ALLOWED_TO_SWITCH']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'not-existing');

        $accessDecisionManager->expects($this->never())
            ->method($decideFunction);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager);

        $this->expectException(AccessDeniedException::class);

        $listener($this->event);
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testSwitchUser($accessDecisionManager, string $decideFunction, bool $returnAsObject)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)->with($token, ['ROLE_ALLOWED_TO_SWITCH'], $this->callback(fn ($user) => 'kuba' === $user->getUserIdentifier()))
            ->willReturn($returnAsObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);

        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')->with($this->callback(fn ($user) => 'kuba' === $user->getUserIdentifier()), $token);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager);
        $listener($this->event);

        $this->assertSame([], $this->request->query->all());
        $this->assertSame('', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(UsernamePasswordToken::class, $this->tokenStorage->getToken());
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testSwitchUserAlreadySwitched($accessDecisionManager, string $decideFunction, bool $returnAsObject)
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('original', null, ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $alreadySwitchedToken = new SwitchUserToken(new InMemoryUser('switched_1', null, ['ROLE_BAR']), 'key', ['ROLE_BAR'], $originalToken);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($alreadySwitchedToken);

        $this->request->query->set('_switch_user', 'kuba');

        $targetsUser = $this->callback(fn ($user) => 'kuba' === $user->getUserIdentifier());
        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)->with($originalToken, ['ROLE_ALLOWED_TO_SWITCH'], $targetsUser)
            ->willReturn($returnAsObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);

        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')->with($targetsUser);

        $listener = new SwitchUserListener($tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', null, false);
        $listener($this->event);

        $this->assertSame([], $this->request->query->all());
        $this->assertSame('', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(SwitchUserToken::class, $tokenStorage->getToken());
        $this->assertSame('kuba', $tokenStorage->getToken()->getUserIdentifier());
        $this->assertSame($originalToken, $tokenStorage->getToken()->getOriginalToken());
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testSwitchUserWorksWithFalsyUsernames($accessDecisionManager, string $decideFunction, bool $returnAsObject)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('kuba', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', '0');

        $this->userProvider->createUser($user = new InMemoryUser('0', null));

        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)->with($token, ['ROLE_ALLOWED_TO_SWITCH'])
            ->willReturn($returnAsObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);

        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')->with($this->callback(fn ($argUser) => $user->isEqualTo($argUser)));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager);
        $listener($this->event);

        $this->assertSame([], $this->request->query->all());
        $this->assertSame('', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(UsernamePasswordToken::class, $this->tokenStorage->getToken());
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testSwitchUserKeepsOtherQueryStringParameters($accessDecisionManager, string $decideFunction, bool $returnAsObject)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->replace([
            '_switch_user' => 'kuba',
            'page' => 3,
            'section' => 2,
        ]);

        $targetsUser = $this->callback(fn ($user) => 'kuba' === $user->getUserIdentifier());
        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)->with($token, ['ROLE_ALLOWED_TO_SWITCH'], $targetsUser)
            ->willReturn($returnAsObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);

        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')->with($targetsUser);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager);
        $listener($this->event);

        $this->assertSame('page=3&section=2', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(UsernamePasswordToken::class, $this->tokenStorage->getToken());
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testSwitchUserWithReplacedToken($accessDecisionManager, string $decideFunction, bool $returnAsObject)
    {
        $user = new InMemoryUser('username', 'password', []);
        $token = new UsernamePasswordToken($user, 'provider123', ['ROLE_FOO']);

        $user = new InMemoryUser('replaced', 'password', []);
        $replacedToken = new UsernamePasswordToken($user, 'provider123', ['ROLE_BAR']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $accessDecisionManager->expects($this->any())
            ->method($decideFunction)->with($token, ['ROLE_ALLOWED_TO_SWITCH'], $this->callback(fn ($user) => 'kuba' === $user->getUserIdentifier()))
            ->willReturn($returnAsObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (SwitchUserEvent $event) use ($replacedToken) {
                    if ('kuba' !== $event->getTargetUser()->getUserIdentifier()) {
                        return false;
                    }
                    $event->setToken($replacedToken);

                    return true;
                }),
                SecurityEvents::SWITCH_USER
            );

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', $dispatcher);
        $listener($this->event);

        $this->assertSame($replacedToken, $this->tokenStorage->getToken());
    }

    public function testSwitchUserThrowsAuthenticationExceptionIfNoCurrentToken()
    {
        $this->tokenStorage->setToken(null);
        $this->request->query->set('_switch_user', 'username');
        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);

        $listener($this->event);
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testSwitchUserStateless($accessDecisionManager, string $decideFunction, bool $returnAsObject)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $targetsUser = $this->callback(fn ($user) => 'kuba' === $user->getUserIdentifier());

        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)->with($token, ['ROLE_ALLOWED_TO_SWITCH'], $targetsUser)
            ->willReturn($returnAsObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);

        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')->with($targetsUser);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', null, true);
        $listener($this->event);

        $this->assertInstanceOf(UsernamePasswordToken::class, $this->tokenStorage->getToken());
        $this->assertFalse($this->event->hasResponse());
    }

    public function testSwitchUserRefreshesOriginalToken()
    {
        $originalUser = new InMemoryUser('username', null);
        $refreshedOriginalUser = new InMemoryUser('username', null);
        $userProvider = $this->createMock(InMemoryUserProvider::class);
        $userProvider
            ->expects($this->any())
            ->method('refreshUser')
            ->with($this->identicalTo($originalUser))
            ->willReturn($refreshedOriginalUser);
        $originalToken = new UsernamePasswordToken($originalUser, 'key');
        $this->tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('username', '', ['ROLE_USER']), 'key', ['ROLE_USER'], $originalToken));
        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(fn (SwitchUserEvent $event) => $event->getToken()->getUser() === $refreshedOriginalUser),
                SecurityEvents::SWITCH_USER
            )
        ;

        $listener = new SwitchUserListener($this->tokenStorage, $userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', $dispatcher);
        $listener($this->event);
    }

    public function provideDataWithAndWithoutVoteObject()
    {
        yield [
            'accessDecisionManager' => $this->createMock(AccessDecisionManagerInterface::class),
            'decideFunction' => 'decide',
            'returnAsObject' => false,
        ];

        yield [
            'accessDecisionManager' => $this
                ->getMockBuilder(AccessDecisionManagerInterface::class)
                ->onlyMethods(['decide'])
                ->addMethods(['getDecision'])
                ->getMock(),
            'decideFunction' => 'getDecision',
            'returnAsObject' => true,
        ];
    }
}

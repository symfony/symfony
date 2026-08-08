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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserChecker;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SwitchUserListenerTest extends TestCase
{
    private TokenStorage $tokenStorage;
    private InMemoryUserProvider $userProvider;
    private UserCheckerInterface $userChecker;
    private AccessDecisionManagerInterface $accessDecisionManager;
    private Request $request;
    private RequestEvent $event;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->userProvider = new InMemoryUserProvider(['kuba' => []]);
        $this->userChecker = new InMemoryUserChecker();
        $this->accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $this->request = new Request();
        $this->event = new RequestEvent($this->createStub(HttpKernelInterface::class), $this->request, HttpKernelInterface::MAIN_REQUEST);
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
        $this->assertFalse($listener->supports($this->event->getRequest()));
    }

    public function testExitUserThrowsAuthenticationExceptionIfNoCurrentToken()
    {
        $this->tokenStorage->setToken(null);
        $this->request->query->set('_switch_user', '_exit');
        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);

        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);
    }

    public function testExitUserThrowsAuthenticationExceptionIfOriginalTokenCannotBeFound()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);

        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);
    }

    public function testExitUserUpdatesToken()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('username', '', []), 'key', []);
        $this->tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('username', '', ['ROLE_USER']), 'key', ['ROLE_USER'], $originalToken));

        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);

        $this->assertSame([], $this->request->query->all());
        $this->assertSame('', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(RedirectResponse::class, $this->event->getResponse());
        $this->assertSame($this->request->getUri(), $this->event->getResponse()->getTargetUrl());
        $this->assertSame($originalToken, $this->tokenStorage->getToken());
    }

    public function testExitUserDoesNotRedirectToTargetRoute()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('username', '', []), 'key', []);
        $this->tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('username', '', ['ROLE_USER']), 'key', ['ROLE_USER'], $originalToken));

        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, urlGenerator: $this->createStub(UrlGeneratorInterface::class), targetRoute: 'whatever');
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);

        $this->assertInstanceOf(RedirectResponse::class, $this->event->getResponse());
        $this->assertSame($this->request->getUri(), $this->event->getResponse()->getTargetUrl());
    }

    public function testExitUserDispatchesEventWithRefreshedUser()
    {
        $originalUser = new InMemoryUser('username', null);
        $refreshedUser = new InMemoryUser('username', null);
        $userProvider = $this->createMock(InMemoryUserProvider::class);
        $userProvider
            ->expects($this->once())
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
                $this->callback(static fn (SwitchUserEvent $event) => $event->getTargetUser() === $refreshedUser),
                SecurityEvents::SWITCH_USER
            )
        ;

        $listener = new SwitchUserListener($this->tokenStorage, $userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', $dispatcher);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);
    }

    public function testSwitchUserIsDisallowed()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $user = new InMemoryUser('username', 'password', []);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, ['ROLE_ALLOWED_TO_SWITCH'])
            ->willReturn(false);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager);

        $this->expectException(AccessDeniedException::class);

        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);
    }

    public function testSwitchUserTurnsAuthenticationExceptionTo403()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_ALLOWED_TO_SWITCH']), 'key', ['ROLE_ALLOWED_TO_SWITCH']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'not-existing');

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->never())
            ->method('decide');

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager);

        $this->expectException(AccessDeniedException::class);

        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);
    }

    public function testSwitchUser()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, ['ROLE_ALLOWED_TO_SWITCH'], $this->callback(static fn ($user) => 'kuba' === $user->getUserIdentifier()))
            ->willReturn(true);

        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
            ->method('checkPostAuth')->with($this->callback(static fn ($user) => 'kuba' === $user->getUserIdentifier()));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $userChecker, 'provider123', $accessDecisionManager);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);

        $this->assertSame([], $this->request->query->all());
        $this->assertSame('', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(UsernamePasswordToken::class, $this->tokenStorage->getToken());
    }

    public function testSwitchUserAlreadySwitched()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('original', null, ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $alreadySwitchedToken = new SwitchUserToken(new InMemoryUser('switched_1', null, ['ROLE_BAR']), 'key', ['ROLE_BAR'], $originalToken);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($alreadySwitchedToken);

        $this->request->query->set('_switch_user', 'kuba');

        $targetsUser = $this->callback(static fn ($user) => 'kuba' === $user->getUserIdentifier());
        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with(self::callback(static function (TokenInterface $token) use ($originalToken, $tokenStorage) {
                // the token storage should also contain the original token for voters depending on it
                return $token === $originalToken && $tokenStorage->getToken() === $originalToken;
            }), ['ROLE_ALLOWED_TO_SWITCH'], $targetsUser)
            ->willReturn(true);

        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
            ->method('checkPostAuth')->with($targetsUser);

        $listener = new SwitchUserListener($tokenStorage, $this->userProvider, $userChecker, 'provider123', $accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', null, false);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);

        $this->assertSame([], $this->request->query->all());
        $this->assertSame('', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(SwitchUserToken::class, $tokenStorage->getToken());
        $this->assertSame('kuba', $tokenStorage->getToken()->getUserIdentifier());
        $this->assertSame($originalToken, $tokenStorage->getToken()->getOriginalToken());
    }

    public function testSwitchUserWorksWithFalsyUsernames()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('kuba', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', '0');

        $this->userProvider->createUser($user = new InMemoryUser('0', null));

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, ['ROLE_ALLOWED_TO_SWITCH'])
            ->willReturn(true);

        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
            ->method('checkPostAuth')->with($this->callback(static fn ($argUser) => $user->isEqualTo($argUser)));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $userChecker, 'provider123', $accessDecisionManager);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);

        $this->assertSame([], $this->request->query->all());
        $this->assertSame('', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(UsernamePasswordToken::class, $this->tokenStorage->getToken());
    }

    public function testSwitchUserKeepsOtherQueryStringParameters()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->replace([
            '_switch_user' => 'kuba',
            'page' => 3,
            'section' => 2,
        ]);

        $targetsUser = $this->callback(static fn ($user) => 'kuba' === $user->getUserIdentifier());
        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, ['ROLE_ALLOWED_TO_SWITCH'], $targetsUser)
            ->willReturn(true);

        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
            ->method('checkPostAuth')->with($targetsUser);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $userChecker, 'provider123', $accessDecisionManager);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);

        $this->assertSame('page=3&section=2', $this->request->server->get('QUERY_STRING'));
        $this->assertInstanceOf(UsernamePasswordToken::class, $this->tokenStorage->getToken());
    }

    public function testSwitchUserWithReplacedToken()
    {
        $user = new InMemoryUser('username', 'password', []);
        $token = new UsernamePasswordToken($user, 'provider123', ['ROLE_FOO']);

        $user = new InMemoryUser('replaced', 'password', []);
        $replacedToken = new UsernamePasswordToken($user, 'provider123', ['ROLE_BAR']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, ['ROLE_ALLOWED_TO_SWITCH'], $this->callback(static fn ($user) => 'kuba' === $user->getUserIdentifier()))
            ->willReturn(true);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (SwitchUserEvent $event) use ($replacedToken) {
                    if ('kuba' !== $event->getTargetUser()->getUserIdentifier()) {
                        return false;
                    }
                    $event->setToken($replacedToken);

                    return true;
                }),
                SecurityEvents::SWITCH_USER
            );

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', $dispatcher);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);

        $this->assertSame($replacedToken, $this->tokenStorage->getToken());
    }

    public function testSwitchUserThrowsAuthenticationExceptionIfNoCurrentToken()
    {
        $this->tokenStorage->setToken(null);
        $this->request->query->set('_switch_user', 'username');
        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);

        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);
    }

    public function testSwitchUserStateless()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);

        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $targetsUser = $this->callback(static fn ($user) => 'kuba' === $user->getUserIdentifier());
        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, ['ROLE_ALLOWED_TO_SWITCH'], $targetsUser)
            ->willReturn(true);

        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
            ->method('checkPostAuth')->with($targetsUser);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $userChecker, 'provider123', $accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', null, true);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);

        $this->assertInstanceOf(UsernamePasswordToken::class, $this->tokenStorage->getToken());
        $this->assertFalse($this->event->hasResponse());
    }

    public function testSwitchUserRefreshesOriginalToken()
    {
        $originalUser = new InMemoryUser('username', null);
        $refreshedOriginalUser = new InMemoryUser('username', null);
        $userProvider = $this->createMock(InMemoryUserProvider::class);
        $userProvider
            ->expects($this->once())
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
                $this->callback(static fn (SwitchUserEvent $event) => $event->getToken()->getUser() === $refreshedOriginalUser),
                SecurityEvents::SWITCH_USER
            )
        ;

        $listener = new SwitchUserListener($this->tokenStorage, $userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', $dispatcher);
        $this->assertTrue($listener->supports($this->event->getRequest()));
        $listener->authenticate($this->event);
    }

    public function testHttpUtilsIsRequiredWhenPathIsSet()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A "Symfony\Component\Security\Http\HttpUtils" instance must be provided when the "path" option is set.');

        new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, path: '/impersonate');
    }

    public function testSupportsReturnsFalseWhenPathDoesNotMatch()
    {
        $this->request->request->set('_switch_user', 'kuba');

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('checkRequestPath')->with($this->request, '/impersonate')
            ->willReturn(false);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, httpUtils: $httpUtils, path: '/impersonate');

        $this->assertFalse($listener->supports($this->request));
    }

    public function testSupportsReadsParameterFromRequestBodyWhenScopedToAPath()
    {
        // when scoped to a path, the listener only fires on a path match and reads
        // the target identity from the request body (as posted by a form)
        $this->request->setMethod('POST');
        $this->request->request->set('_switch_user', 'kuba');

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('checkRequestPath')->with($this->request, '/impersonate')
            ->willReturn(true);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, httpUtils: $httpUtils, path: '/impersonate');

        $this->assertTrue($listener->supports($this->request));
        $this->assertSame('kuba', $this->request->attributes->get('_switch_user_username'));
    }

    public function testSwitchUserViaPathRedirectsToTargetPath()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $this->tokenStorage->setToken($token);

        $this->request->setMethod('POST');
        $this->request->request->set('_switch_user', 'kuba');
        $this->request->request->set('_target_path', '/dashboard');

        $httpUtils = $this->createStub(HttpUtils::class);
        $httpUtils->method('checkRequestPath')->willReturn(true);
        $httpUtils->method('createRedirectResponse')->willReturnCallback(static fn ($request, $path) => new RedirectResponse($path));

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, ['ROLE_ALLOWED_TO_SWITCH'])
            ->willReturn(true);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, httpUtils: $httpUtils, path: '/impersonate');
        $this->assertTrue($listener->supports($this->request));
        $listener->authenticate($this->event);

        $this->assertInstanceOf(SwitchUserToken::class, $this->tokenStorage->getToken());
        $this->assertInstanceOf(RedirectResponse::class, $this->event->getResponse());
        $this->assertSame('/dashboard', $this->event->getResponse()->getTargetUrl());
    }

    public function testSwitchUserViaPathFallsBackToRootWhenNoTarget()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $this->tokenStorage->setToken($token);

        $this->request->setMethod('POST');
        $this->request->request->set('_switch_user', 'kuba');

        $httpUtils = $this->createStub(HttpUtils::class);
        $httpUtils->method('checkRequestPath')->willReturn(true);
        $httpUtils->method('createRedirectResponse')->willReturnCallback(static fn ($request, $path) => new RedirectResponse($path));

        $accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $accessDecisionManager->method('decide')->willReturn(true);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, httpUtils: $httpUtils, path: '/impersonate');
        $this->assertTrue($listener->supports($this->request));
        $listener->authenticate($this->event);

        $this->assertSame('/', $this->event->getResponse()->getTargetUrl());
    }

    public function testSwitchUserViaPathNeutralizesOpenRedirectTargetPath()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $this->tokenStorage->setToken($token);

        $request = Request::create('/impersonate', 'POST');
        $request->request->set('_switch_user', 'kuba');
        $request->request->set('_target_path', '//attacker.com');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $accessDecisionManager->method('decide')->willReturn(true);

        // a real HttpUtils routes the target through getUriForPath(), keeping the current host
        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, httpUtils: new HttpUtils(), path: '/impersonate');
        $this->assertTrue($listener->supports($request));
        $listener->authenticate($event);

        $this->assertSame('http://localhost//attacker.com', $event->getResponse()->getTargetUrl());
    }

    public function testSwitchUserFailsWithMissingCsrfToken()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_ALLOWED_TO_SWITCH']), 'key', ['ROLE_ALLOWED_TO_SWITCH']);
        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, csrfTokenManager: $csrfTokenManager);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $this->assertTrue($listener->supports($this->request));
        $listener->authenticate($this->event);
    }

    public function testSwitchUserFailsWithInvalidCsrfToken()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_ALLOWED_TO_SWITCH']), 'key', ['ROLE_ALLOWED_TO_SWITCH']);
        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');
        $this->request->query->set('_csrf_token', 'invalid');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')->with(new CsrfToken('switch_user', 'invalid'))
            ->willReturn(false);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, csrfTokenManager: $csrfTokenManager);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $this->assertTrue($listener->supports($this->request));
        $listener->authenticate($this->event);
    }

    public function testSwitchUserSucceedsWithValidCsrfToken()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $this->tokenStorage->setToken($token);
        $this->request->query->set('_switch_user', 'kuba');
        $this->request->query->set('_csrf_token', 'valid');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')->with(new CsrfToken('switch_user', 'valid'))
            ->willReturn(true);

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, ['ROLE_ALLOWED_TO_SWITCH'])
            ->willReturn(true);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, csrfTokenManager: $csrfTokenManager);
        $this->assertTrue($listener->supports($this->request));
        $listener->authenticate($this->event);

        $this->assertInstanceOf(SwitchUserToken::class, $this->tokenStorage->getToken());
    }

    public function testExitUserFailsWithInvalidCsrfToken()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('username', '', []), 'key', []);
        $switchUserToken = new SwitchUserToken(new InMemoryUser('kuba', '', ['ROLE_USER']), 'key', ['ROLE_USER'], $originalToken);
        $this->tokenStorage->setToken($switchUserToken);
        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, csrfTokenManager: $csrfTokenManager);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $this->assertTrue($listener->supports($this->request));
        $listener->authenticate($this->event);
    }

    public function testCsrfTokenIsRemovedFromTheRedirectUrl()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', '', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $this->tokenStorage->setToken($token);

        $request = Request::create('/page?_switch_user=kuba&_csrf_token=T0K3N&keep=1');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $accessDecisionManager->method('decide')->willReturn(true);

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $accessDecisionManager, csrfTokenManager: $csrfTokenManager);
        $this->assertTrue($listener->supports($request));
        $listener->authenticate($event);

        $this->assertSame(['keep' => '1'], $request->query->all());
        $this->assertSame('http://localhost/page?keep=1', $event->getResponse()->getTargetUrl());
    }

    public function testExitUserViaPathDoesNotRedirectToTargetRoute()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('username', '', []), 'key', []);
        $this->tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('kuba', '', ['ROLE_USER']), 'key', ['ROLE_USER'], $originalToken));

        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);

        $httpUtils = $this->createStub(HttpUtils::class);
        $httpUtils->method('checkRequestPath')->willReturn(true);
        $httpUtils->method('createRedirectResponse')->willReturnCallback(static fn ($request, $path) => new RedirectResponse($path));

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/after-switch');

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, urlGenerator: $urlGenerator, targetRoute: 'whatever', httpUtils: $httpUtils, path: '/impersonate');
        $this->assertTrue($listener->supports($this->request));
        $listener->authenticate($this->event);

        $this->assertSame('/', $this->event->getResponse()->getTargetUrl());
        $this->assertSame($originalToken, $this->tokenStorage->getToken());
    }

    public function testExitUserViaPathRedirectsToTargetPath()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('username', '', []), 'key', []);
        $this->tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('kuba', '', ['ROLE_USER']), 'key', ['ROLE_USER'], $originalToken));

        $this->request->query->set('_switch_user', SwitchUserListener::EXIT_VALUE);
        $this->request->query->set('_target_path', '/dashboard');

        $httpUtils = $this->createStub(HttpUtils::class);
        $httpUtils->method('checkRequestPath')->willReturn(true);
        $httpUtils->method('createRedirectResponse')->willReturnCallback(static fn ($request, $path) => new RedirectResponse($path));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, httpUtils: $httpUtils, path: '/impersonate');
        $this->assertTrue($listener->supports($this->request));
        $listener->authenticate($this->event);

        $this->assertSame('/dashboard', $this->event->getResponse()->getTargetUrl());
        $this->assertSame($originalToken, $this->tokenStorage->getToken());
    }
}

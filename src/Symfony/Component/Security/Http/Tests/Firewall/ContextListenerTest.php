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
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\UsageTrackingTokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Firewall\ContextListener;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class ContextListenerTest extends TestCase
{
    public function testItRequiresContextKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$contextKey must not be empty');
        new ContextListener(
            $this->createMock(TokenStorageInterface::class),
            [],
            ''
        );
    }

    public function testUserProvidersNeedToImplementAnInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User provider "stdClass" must implement "Symfony\Component\Security\Core\User\UserProviderInterface');
        $this->handleEventWithPreviousSession([new \stdClass()]);
    }

    public function testOnKernelResponseWillAddSession()
    {
        $session = $this->runSessionOnKernelResponse(
            new UsernamePasswordToken(new InMemoryUser('test1', 'pass1'), 'phpunit', ['ROLE_USER']),
            null
        );

        $token = unserialize($session->get('_security_session'));
        $this->assertInstanceOf(UsernamePasswordToken::class, $token);
        $this->assertEquals('test1', $token->getUserIdentifier());
    }

    public function testOnKernelResponseWillReplaceSession()
    {
        $session = $this->runSessionOnKernelResponse(
            new UsernamePasswordToken(new InMemoryUser('test1', 'pass1'), 'phpunit', ['ROLE_USER']),
            'C:10:"serialized"'
        );

        $token = unserialize($session->get('_security_session'));
        $this->assertInstanceOf(UsernamePasswordToken::class, $token);
        $this->assertEquals('test1', $token->getUserIdentifier());
    }

    public function testOnKernelResponseWillRemoveSession()
    {
        $session = $this->runSessionOnKernelResponse(
            null,
            'C:10:"serialized"'
        );

        $this->assertFalse($session->has('_security_session'));
    }

    public function testOnKernelResponseWithoutSession()
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('test1', 'pass1'), 'phpunit', ['ROLE_USER']));
        $request = new Request();
        $request->attributes->set('_security_firewall_run', '_security_session');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        );

        $listener = new ContextListener($tokenStorage, [], 'session', null, new EventDispatcher());
        $listener->onKernelResponse($event);

        $this->assertTrue($session->isStarted());
    }

    public function testOnKernelResponseWithoutSessionNorToken()
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        );

        $listener = new ContextListener(new TokenStorage(), [], 'session', null, new EventDispatcher());
        $listener->onKernelResponse($event);

        $this->assertFalse($session->isStarted());
    }

    /**
     * @dataProvider provideInvalidToken
     */
    public function testInvalidTokenInSession($token)
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->any())->method('getName')->willReturn('SESSIONNAME');
        $session->expects($this->any())
            ->method('get')
            ->with('_security_key123')
            ->willReturn($token);
        $request = new Request([], [], [], ['SESSIONNAME' => true]);
        $request->setSession($session);

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $listener = new ContextListener($tokenStorage, [], 'key123');
        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public static function provideInvalidToken()
    {
        return [
            ['foo'],
            ['O:8:"NotFound":0:{}'],
            [serialize(new \__PHP_Incomplete_Class())],
            [serialize(null)],
            [null],
        ];
    }

    public function testHandleAddsKernelResponseListener()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $listener = new ContextListener($tokenStorage, [], 'key123', null, $dispatcher);

        $dispatcher->expects($this->once())
            ->method('addListener')
            ->with(KernelEvents::RESPONSE, $listener->onKernelResponse(...));

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
    }

    public function testOnKernelResponseListenerRemovesItself()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->any())->method('getName')->willReturn('SESSIONNAME');
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $listener = new ContextListener($tokenStorage, [], 'key123', null, $dispatcher);

        $request = new Request();
        $request->attributes->set('_security_firewall_run', '_security_key123');
        $request->setSession($session);

        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());

        $dispatcher->expects($this->once())
            ->method('removeListener')
            ->with(KernelEvents::RESPONSE, $listener->onKernelResponse(...));

        $listener->onKernelResponse($event);
    }

    public function testHandleRemovesTokenIfNoPreviousSessionWasFound()
    {
        $request = new Request();

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('setToken')->with(null);

        $listener = new ContextListener($tokenStorage, [], 'key123');
        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testIfTokenIsDeauthenticated()
    {
        $refreshedUser = new InMemoryUser('foobar', 'baz');
        $tokenStorage = $this->handleEventWithPreviousSession([new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)]);

        $this->assertNull($tokenStorage->getToken());
    }

    public function testTokenIsNotDeauthenticatedOnUserChangeIfNotAnInstanceOfAbstractToken()
    {
        $tokenStorage = new TokenStorage();
        $refreshedUser = new InMemoryUser('changed', 'baz');

        $token = new CustomToken(new InMemoryUser('original', 'foo'), ['ROLE_FOO']);

        $session = new Session(new MockArraySessionStorage());
        $session->set('_security_context_key', serialize($token));

        $request = new Request();
        $request->setSession($session);
        $request->cookies->set('MOCKSESSID', true);

        $listener = new ContextListener($tokenStorage, [new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)], 'context_key');
        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertInstanceOf(CustomToken::class, $tokenStorage->getToken());
        $this->assertSame($refreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testIfTokenIsNotDeauthenticated()
    {
        $tokenStorage = new TokenStorage();
        $badRefreshedUser = new InMemoryUser('foobar', 'baz');
        $goodRefreshedUser = new InMemoryUser('foobar', 'bar');
        $tokenStorage = $this->handleEventWithPreviousSession([new SupportingUserProvider($badRefreshedUser), new SupportingUserProvider($goodRefreshedUser)], $goodRefreshedUser);
        $this->assertSame($goodRefreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testTryAllUserProvidersUntilASupportingUserProviderIsFound()
    {
        $refreshedUser = new InMemoryUser('foobar', 'baz');
        $tokenStorage = $this->handleEventWithPreviousSession([new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)], $refreshedUser);

        $this->assertSame($refreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testNextSupportingUserProviderIsTriedIfPreviousSupportingUserProviderDidNotLoadTheUser()
    {
        $refreshedUser = new InMemoryUser('foobar', 'baz');
        $tokenStorage = $this->handleEventWithPreviousSession([new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)], $refreshedUser);

        $this->assertSame($refreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testTokenIsSetToNullIfNoUserWasLoadedByTheRegisteredUserProviders()
    {
        $tokenStorage = $this->handleEventWithPreviousSession([new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider()]);

        $this->assertNull($tokenStorage->getToken());
    }

    public function testRuntimeExceptionIsThrownIfNoSupportingUserProviderWasRegistered()
    {
        $this->expectException(\RuntimeException::class);
        $this->handleEventWithPreviousSession([new NotSupportingUserProvider(false), new NotSupportingUserProvider(true)]);
    }

    public function testAcceptsProvidersAsTraversable()
    {
        $refreshedUser = new InMemoryUser('foobar', 'baz');
        $tokenStorage = $this->handleEventWithPreviousSession(new \ArrayObject([new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)]), $refreshedUser);

        $this->assertSame($refreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testWithPreviousNotStartedSession()
    {
        $session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($session);
        $request->cookies->set('MOCKSESSID', true);

        $usageIndex = $session->getUsageIndex();

        $tokenStorage = new TokenStorage();
        $listener = new ContextListener($tokenStorage, [], 'context_key', null, null, null, $tokenStorage->getToken(...));
        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame($usageIndex, $session->getUsageIndex());
    }

    public function testSessionIsNotReported()
    {
        $usageReporter = $this->getMockBuilder(\stdClass::class)->addMethods(['__invoke'])->getMock();
        $usageReporter->expects($this->never())->method('__invoke');

        $session = new Session(new MockArraySessionStorage(), null, null, $usageReporter);

        $request = new Request();
        $request->setSession($session);
        $request->cookies->set('MOCKSESSID', true);

        $tokenStorage = new TokenStorage();

        $listener = new ContextListener($tokenStorage, [], 'context_key', null, null, null, $tokenStorage->getToken(...));
        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testOnKernelResponseRemoveListener()
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('test1', 'pass1'), 'phpunit', ['ROLE_USER']));

        $request = new Request();
        $request->attributes->set('_security_firewall_run', '_security_session');

        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $dispatcher = new EventDispatcher();
        $httpKernel = $this->createMock(HttpKernelInterface::class);

        $listener = new ContextListener($tokenStorage, [], 'session', null, $dispatcher, null, $tokenStorage->getToken(...));
        $this->assertEmpty($dispatcher->getListeners());

        $listener(new RequestEvent($httpKernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $this->assertNotEmpty($dispatcher->getListeners());

        $listener->onKernelResponse(new ResponseEvent($httpKernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response()));
        $this->assertEmpty($dispatcher->getListeners());
    }

    protected function runSessionOnKernelResponse($newToken, $original = null)
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->attributes->set('_security_firewall_run', '_security_session');
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        if (null !== $original) {
            $session->set('_security_session', $original);
        }

        $factories = ['request_stack' => fn () => $requestStack];
        $tokenStorage = new UsageTrackingTokenStorage(new TokenStorage(), new class($factories) implements ContainerInterface {
            use ServiceLocatorTrait;
        });

        $tokenStorage->setToken($newToken);

        $request->cookies->set('MOCKSESSID', true);

        $sessionId = $session->getId();
        $usageIndex = $session->getUsageIndex();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        );

        $listener = new ContextListener($tokenStorage, [], 'session', null, new EventDispatcher(), null, $tokenStorage->enableUsageTracking(...));
        $listener->onKernelResponse($event);

        if ($session->getId() === $sessionId) {
            $this->assertSame($usageIndex, $session->getUsageIndex());
        } else {
            $this->assertNotSame($usageIndex, $session->getUsageIndex());
        }

        return $session;
    }

    private function handleEventWithPreviousSession($userProviders, UserInterface $user = null)
    {
        $tokenUser = $user ?? new InMemoryUser('foo', 'bar');
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security_context_key', serialize(new UsernamePasswordToken($tokenUser, 'context_key', ['ROLE_USER'])));

        $request = new Request();
        $request->setSession($session);
        $request->cookies->set('MOCKSESSID', true);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tokenStorage = new TokenStorage();
        $usageIndex = $session->getUsageIndex();

        $factories = ['request_stack' => fn () => $requestStack];
        $tokenStorage = new UsageTrackingTokenStorage($tokenStorage, new class($factories) implements ContainerInterface {
            use ServiceLocatorTrait;
        });
        $sessionTrackerEnabler = $tokenStorage->enableUsageTracking(...);

        $listener = new ContextListener($tokenStorage, $userProviders, 'context_key', null, null, null, $sessionTrackerEnabler);

        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        if (null !== $user) {
            ++$usageIndex;
        }

        $this->assertSame($usageIndex, $session->getUsageIndex());
        $tokenStorage->getToken();
        $this->assertSame(1 + $usageIndex, $session->getUsageIndex());

        return $tokenStorage;
    }
}

class NotSupportingUserProvider implements UserProviderInterface
{
    /** @var bool */
    private $throwsUnsupportedException;

    public function __construct($throwsUnsupportedException)
    {
        $this->throwsUnsupportedException = $throwsUnsupportedException;
    }

    public function loadUserByUsername($username): UserInterface
    {
        throw new UserNotFoundException();
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($this->throwsUnsupportedException) {
            throw new UnsupportedUserException();
        }

        return $user;
    }

    public function supportsClass($class): bool
    {
        return false;
    }
}

class SupportingUserProvider implements UserProviderInterface
{
    private $refreshedUser;

    public function __construct(InMemoryUser $refreshedUser = null)
    {
        $this->refreshedUser = $refreshedUser;
    }

    public function loadUserByUsername($username): UserInterface
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof InMemoryUser) {
            throw new UnsupportedUserException();
        }

        if (null === $this->refreshedUser) {
            throw new UserNotFoundException();
        }

        return $this->refreshedUser;
    }

    public function supportsClass($class): bool
    {
        return InMemoryUser::class === $class;
    }
}

class CustomToken implements TokenInterface
{
    private $user;
    private $roles;

    public function __construct(UserInterface $user, array $roles)
    {
        $this->user = $user;
        $this->roles = $roles;
    }

    public function __serialize(): array
    {
        return [$this->user, $this->roles];
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function __unserialize(array $data): void
    {
        [$this->user, $this->roles] = $data;
    }

    public function unserialize($serialized)
    {
        $this->__unserialize(\is_array($serialized) ? $serialized : unserialize($serialized));
    }

    public function __toString(): string
    {
        return $this->user->getUserIdentifier();
    }

    public function getRoleNames(): array
    {
        return $this->roles;
    }

    public function getCredentials()
    {
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser($user): void
    {
        $this->user = $user;
    }

    public function getUsername(): string
    {
        return $this->user->getUserIdentifier();
    }

    public function getUserIdentifier(): string
    {
        return $this->getUserIdentifier();
    }

    public function isAuthenticated(): bool
    {
        return true;
    }

    public function setAuthenticated(bool $isAuthenticated)
    {
    }

    public function eraseCredentials(): void
    {
    }

    public function getAttributes(): array
    {
        return [];
    }

    public function setAttributes(array $attributes): void
    {
    }

    public function hasAttribute(string $name): bool
    {
        return false;
    }

    public function getAttribute(string $name): mixed
    {
        return null;
    }

    public function setAttribute(string $name, $value): void
    {
    }
}

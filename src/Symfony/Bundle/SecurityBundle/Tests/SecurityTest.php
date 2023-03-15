<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

class SecurityTest extends TestCase
{
    public function testGetToken()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', 'bar'), 'provider');
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $container = $this->createContainer('security.token_storage', $tokenStorage);

        $security = new Security($container);
        $this->assertSame($token, $security->getToken());
    }

    /**
     * @dataProvider getUserTests
     */
    public function testGetUser($userInToken, $expectedUser)
    {
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($userInToken);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $container = $this->createContainer('security.token_storage', $tokenStorage);

        $security = new Security($container);
        $this->assertSame($expectedUser, $security->getUser());
    }

    public static function getUserTests()
    {
        yield [null, null];

        $user = new InMemoryUser('nice_user', 'foo');
        yield [$user, $user];
    }

    public function testIsGranted()
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('SOME_ATTRIBUTE', 'SOME_SUBJECT')
            ->willReturn(true);

        $container = $this->createContainer('security.authorization_checker', $authorizationChecker);

        $security = new Security($container);
        $this->assertTrue($security->isGranted('SOME_ATTRIBUTE', 'SOME_SUBJECT'));
    }

    /**
     * @dataProvider getFirewallConfigTests
     */
    public function testGetFirewallConfig(Request $request, ?FirewallConfig $expectedFirewallConfig)
    {
        $firewallMap = $this->createMock(FirewallMap::class);

        $firewallMap->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn($expectedFirewallConfig);

        $container = $this->createContainer('security.firewall.map', $firewallMap);

        $security = new Security($container);
        $this->assertSame($expectedFirewallConfig, $security->getFirewallConfig($request));
    }

    public static function getFirewallConfigTests()
    {
        $request = new Request();

        yield [$request, null];
        yield [$request, new FirewallConfig('main', 'acme_user_checker')];
    }

    public function testLogin()
    {
        $request = new Request();
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $requestStack = $this->createMock(RequestStack::class);
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewall = new FirewallConfig('main', 'main');
        $userAuthenticator = $this->createMock(UserAuthenticatorInterface::class);
        $user = $this->createMock(UserInterface::class);
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['request_stack', $requestStack],
                ['security.firewall.map', $firewallMap],
                ['security.authenticator.managers_locator', $this->createContainer('main', $userAuthenticator)],
                ['security.user_checker', $userChecker],
            ])
        ;

        $requestStack->expects($this->once())->method('getCurrentRequest')->willReturn($request);
        $firewallMap->expects($this->once())->method('getFirewallConfig')->willReturn($firewall);
        $userAuthenticator->expects($this->once())->method('authenticateUser')->with($user, $authenticator, $request);
        $userChecker->expects($this->once())->method('checkPreAuth')->with($user);

        $firewallAuthenticatorLocator = $this->createMock(ServiceProviderInterface::class);
        $firewallAuthenticatorLocator
            ->expects($this->once())
            ->method('getProvidedServices')
            ->willReturn(['security.authenticator.custom.dev' => $authenticator])
        ;
        $firewallAuthenticatorLocator
            ->expects($this->once())
            ->method('get')
            ->with('security.authenticator.custom.dev')
            ->willReturn($authenticator)
        ;

        $security = new Security($container, ['main' => $firewallAuthenticatorLocator]);

        $security->login($user);
    }

    public function testLoginReturnsAuthenticatorResponse()
    {
        $request = new Request();
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $requestStack = $this->createMock(RequestStack::class);
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewall = new FirewallConfig('main', 'main');
        $user = $this->createMock(UserInterface::class);
        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userAuthenticator = $this->createMock(UserAuthenticatorInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['request_stack', $requestStack],
                ['security.firewall.map', $firewallMap],
                ['security.authenticator.managers_locator', $this->createContainer('main', $userAuthenticator)],
                ['security.user_checker', $userChecker],
            ])
        ;

        $requestStack->expects($this->once())->method('getCurrentRequest')->willReturn($request);
        $firewallMap->expects($this->once())->method('getFirewallConfig')->willReturn($firewall);
        $userChecker->expects($this->once())->method('checkPreAuth')->with($user);
        $userAuthenticator->expects($this->once())->method('authenticateUser')
            ->with($user, $authenticator, $request)
            ->willReturn(new Response('authenticator response'));

        $firewallAuthenticatorLocator = $this->createMock(ServiceProviderInterface::class);
        $firewallAuthenticatorLocator
            ->expects($this->once())
            ->method('getProvidedServices')
            ->willReturn(['security.authenticator.custom.dev' => $authenticator])
        ;
        $firewallAuthenticatorLocator
            ->expects($this->once())
            ->method('get')
            ->with('security.authenticator.custom.dev')
            ->willReturn($authenticator)
        ;

        $security = new Security($container, ['main' => $firewallAuthenticatorLocator]);

        $response = $security->login($user);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('authenticator response', $response->getContent());
    }

    public function testLoginWithoutAuthenticatorThrows()
    {
        $request = new Request();
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $requestStack = $this->createMock(RequestStack::class);
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewall = new FirewallConfig('main', 'main');
        $user = $this->createMock(UserInterface::class);
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['request_stack', $requestStack],
                ['security.firewall.map', $firewallMap],
                ['security.user_checker', $userChecker],
            ])
        ;

        $requestStack->expects($this->once())->method('getCurrentRequest')->willReturn($request);
        $firewallMap->expects($this->once())->method('getFirewallConfig')->willReturn($firewall);

        $security = new Security($container, ['main' => null]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No authenticators found for firewall "main".');

        $security->login($user);
    }

    public function testLogout()
    {
        $request = new Request();
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())->method('getMainRequest')->willReturn($request);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(new InMemoryUser('foo', 'bar'));
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('getToken')->willReturn($token);
        $tokenStorage->expects($this->once())->method('setToken')->with(null);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(new LogoutEvent($request, $token))
        ;

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallConfig = new FirewallConfig('my_firewall', 'user_checker');
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->willReturn($firewallConfig)
        ;

        $eventDispatcherLocator = $this->createMock(ContainerInterface::class);
        $eventDispatcherLocator
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['my_firewall', $eventDispatcher],
            ])
        ;

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['request_stack', $requestStack],
                ['security.token_storage', $tokenStorage],
                ['security.firewall.map', $firewallMap],
                ['security.firewall.event_dispatcher_locator', $eventDispatcherLocator],
            ])
        ;
        $security = new Security($container);
        $security->logout(false);
    }

    public function testLogoutWithoutFirewall()
    {
        $request = new Request();
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())->method('getMainRequest')->willReturn($request);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(new InMemoryUser('foo', 'bar'));
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->willReturn($token)
        ;

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->willReturn(null)
        ;

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['request_stack', $requestStack],
                ['security.token_storage', $tokenStorage],
                ['security.firewall.map', $firewallMap],
            ])
        ;

        $this->expectException(LogicException::class);
        $security = new Security($container);
        $security->logout(false);
    }

    public function testLogoutWithResponse()
    {
        $request = new Request();
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())->method('getMainRequest')->willReturn($request);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(new InMemoryUser('foo', 'bar'));
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('getToken')->willReturn($token);
        $tokenStorage->expects($this->once())->method('setToken')->with(null);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($request, $token) {
                $this->assertInstanceOf(LogoutEvent::class, $event);
                $this->assertEquals($request, $event->getRequest());
                $this->assertEquals($token, $event->getToken());

                $event->setResponse(new Response('a custom response'));

                return $event;
            })
        ;

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallConfig = new FirewallConfig('my_firewall', 'user_checker');
        $firewallMap->expects($this->once())->method('getFirewallConfig')->willReturn($firewallConfig);

        $eventDispatcherLocator = $this->createMock(ContainerInterface::class);
        $eventDispatcherLocator
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([['my_firewall', $eventDispatcher]])
        ;

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['request_stack', $requestStack],
                ['security.token_storage', $tokenStorage],
                ['security.firewall.map', $firewallMap],
                ['security.firewall.event_dispatcher_locator', $eventDispatcherLocator],
            ])
        ;
        $security = new Security($container);
        $response = $security->logout(false);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('a custom response', $response->getContent());
    }

    public function testLogoutWithValidCsrf()
    {
        $request = new Request(['_csrf_token' => 'dummytoken']);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())->method('getMainRequest')->willReturn($request);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(new InMemoryUser('foo', 'bar'));
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('getToken')->willReturn($token);
        $tokenStorage->expects($this->once())->method('setToken')->with(null);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($request, $token) {
                $this->assertInstanceOf(LogoutEvent::class, $event);
                $this->assertEquals($request, $event->getRequest());
                $this->assertEquals($token, $event->getToken());

                $event->setResponse(new Response('a custom response'));

                return $event;
            })
        ;

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallConfig = new FirewallConfig(name: 'my_firewall', userChecker: 'user_checker', logout: ['csrf_parameter' => '_csrf_token', 'csrf_token_id' => 'logout']);
        $firewallMap->expects($this->once())->method('getFirewallConfig')->willReturn($firewallConfig);

        $eventDispatcherLocator = $this->createMock(ContainerInterface::class);
        $eventDispatcherLocator
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([['my_firewall', $eventDispatcher]])
        ;

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())->method('isTokenValid')->with($this->equalTo(new CsrfToken('logout', 'dummytoken')))->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('has')->with('security.csrf.token_manager')->willReturn(true);
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['request_stack', $requestStack],
                ['security.token_storage', $tokenStorage],
                ['security.firewall.map', $firewallMap],
                ['security.firewall.event_dispatcher_locator', $eventDispatcherLocator],
                ['security.csrf.token_manager', $csrfTokenManager],
            ])
        ;
        $security = new Security($container);
        $response = $security->logout();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('a custom response', $response->getContent());
    }

    private function createContainer(string $serviceId, object $serviceObject): ContainerInterface
    {
        return new ServiceLocator([$serviceId => fn () => $serviceObject]);
    }
}

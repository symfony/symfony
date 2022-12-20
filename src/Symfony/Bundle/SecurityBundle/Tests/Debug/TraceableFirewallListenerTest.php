<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticatorManagerListener;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Firewall\AuthenticatorManagerListener;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * @group time-sensitive
 */
class TraceableFirewallListenerTest extends TestCase
{
    public function testOnKernelRequestRecordsListeners()
    {
        $request = new Request();
        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse($response = new Response());
        $listener = function ($e) use ($event, &$listenerCalled) {
            $listenerCalled += $e === $event;
        };
        $firewallMap = self::createMock(FirewallMap::class);
        $firewallMap
            ->expects(self::once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects(self::once())
            ->method('getListeners')
            ->with($request)
            ->willReturn([[$listener], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator());
        $firewall->configureLogoutUrlGenerator($event);
        $firewall->onKernelRequest($event);

        $listeners = $firewall->getWrappedListeners();
        self::assertCount(1, $listeners);
        self::assertSame($listener, $listeners[0]['stub']);
    }

    public function testOnKernelRequestRecordsAuthenticatorsInfo()
    {
        $request = new Request();

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse($response = new Response());

        $supportingAuthenticator = self::createMock(DummyAuthenticator::class);
        $supportingAuthenticator
            ->method('supports')
            ->with($request)
            ->willReturn(true);
        $supportingAuthenticator
            ->expects(self::once())
            ->method('authenticate')
            ->with($request)
            ->willReturn(new SelfValidatingPassport(new UserBadge('robin', function () {})));
        $supportingAuthenticator
            ->expects(self::once())
            ->method('onAuthenticationSuccess')
            ->willReturn($response);
        $supportingAuthenticator
            ->expects(self::once())
            ->method('createToken')
            ->willReturn(self::createMock(TokenInterface::class));

        $notSupportingAuthenticator = self::createMock(DummyAuthenticator::class);
        $notSupportingAuthenticator
            ->method('supports')
            ->with($request)
            ->willReturn(false);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $dispatcher = new EventDispatcher();
        $authenticatorManager = new AuthenticatorManager(
            [$notSupportingAuthenticator, $supportingAuthenticator],
            $tokenStorage,
            $dispatcher,
            'main'
        );

        $listener = new TraceableAuthenticatorManagerListener(new AuthenticatorManagerListener($authenticatorManager));
        $firewallMap = self::createMock(FirewallMap::class);
        $firewallMap
            ->expects(self::once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects(self::once())
            ->method('getListeners')
            ->with($request)
            ->willReturn([[$listener], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, $dispatcher, new LogoutUrlGenerator());
        $firewall->configureLogoutUrlGenerator($event);
        $firewall->onKernelRequest($event);

        self::assertCount(2, $authenticatorsInfo = $firewall->getAuthenticatorsInfo());

        self::assertFalse($authenticatorsInfo[0]['supports']);
        self::assertStringContainsString('DummyAuthenticator', $authenticatorsInfo[0]['stub']);

        self::assertTrue($authenticatorsInfo[1]['supports']);
        self::assertStringContainsString('DummyAuthenticator', $authenticatorsInfo[1]['stub']);
    }
}

abstract class DummyAuthenticator implements InteractiveAuthenticatorInterface
{
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
    }
}

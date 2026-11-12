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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Bundle\SecurityBundle\Security\LazyFirewallContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticatorManagerListener;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\AuthenticatorManagerListener;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

#[Group('time-sensitive')]
class TraceableFirewallListenerTest extends TestCase
{
    public function testOnKernelRequestRecordsListeners()
    {
        $request = new Request();
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse(new Response());
        $listener = new class extends AbstractListener {
            public int $callCount = 0;

            public function supports(Request $request): ?bool
            {
                return true;
            }

            public function authenticate(RequestEvent $event): void
            {
                ++$this->callCount;
            }
        };
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects($this->once())
            ->method('getListeners')
            ->with($request)
            ->willReturn([[$listener], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator());
        $firewall->configureLogoutUrlGenerator($event);
        $firewall->onKernelRequest($event);

        $listeners = $firewall->getWrappedListeners();
        $this->assertCount(1, $listeners);
        $this->assertSame($listener, $listeners[0]['stub']);
    }

    public function testOnKernelRequestRecordsAuthenticatorsInfo()
    {
        $request = new Request();

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse($response = new Response());

        $supportingAuthenticator = $this->createMock(DummyAuthenticator::class);
        $supportingAuthenticator
            ->method('supports')
            ->willReturnMap([
                [$request, true],
            ]);
        $supportingAuthenticator
            ->expects($this->once())
            ->method('authenticate')
            ->with($request)
            ->willReturn(new SelfValidatingPassport(new UserBadge('robin', static function () {})));
        $supportingAuthenticator
            ->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->willReturn($response);
        $supportingAuthenticator
            ->expects($this->once())
            ->method('createToken')
            ->willReturn(new class extends AbstractToken {});

        $notSupportingAuthenticator = $this->createMock(DummyAuthenticator::class);
        $notSupportingAuthenticator
            ->expects($this->once())
            ->method('supports')
            ->with($request)
            ->willReturn(false);

        $dispatcher = new EventDispatcher();
        $authenticatorManager = new AuthenticatorManager(
            [new TraceableAuthenticator($notSupportingAuthenticator), new TraceableAuthenticator($supportingAuthenticator)],
            new TokenStorage(),
            $dispatcher,
            'main'
        );

        $listener = new TraceableAuthenticatorManagerListener(new AuthenticatorManagerListener($authenticatorManager));
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects($this->once())
            ->method('getListeners')
            ->with($request)
            ->willReturn([[$listener], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, $dispatcher, new LogoutUrlGenerator());
        $firewall->configureLogoutUrlGenerator($event);
        $firewall->onKernelRequest($event);

        $this->assertCount(2, $authenticatorsInfo = $firewall->getAuthenticatorsInfo());

        $this->assertFalse($authenticatorsInfo[0]['supports']);
        $this->assertStringContainsString('DummyAuthenticator', $authenticatorsInfo[0]['stub']);

        $this->assertTrue($authenticatorsInfo[1]['supports']);
        $this->assertStringContainsString('DummyAuthenticator', $authenticatorsInfo[1]['stub']);
    }

    public function testOnKernelRequestDoesNotWrapTheListenersOfALazyContextTwice()
    {
        $this->assertListenersOfALazyContextAreWrappedOnce(true);
    }

    public function testOnKernelRequestDoesNotWrapTheDeferredListenersOfALazyContextTwice()
    {
        $this->assertListenersOfALazyContextAreWrappedOnce(null);
    }

    public function testOnKernelRequestPutsTheListenersOfALazyContextBackWhenOneThrows()
    {
        $request = new Request();
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener = new class extends AbstractListener {
            public function supports(Request $request): ?bool
            {
                return true;
            }

            public function authenticate(RequestEvent $event): void
            {
                throw new AccessDeniedException();
            }
        };
        $context = new LazyFirewallContext([$listener], null, null, null, new TokenStorage());
        $firewallMap = $this->createStub(FirewallMap::class);
        $firewallMap->method('getListeners')->willReturn([[$context], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator());

        try {
            $firewall->onKernelRequest($event);
            $this->fail('An AccessDeniedException should have been thrown.');
        } catch (AccessDeniedException) {
        }

        $this->assertSame([$listener], \Closure::bind(fn () => $this->listeners, $context, FirewallContext::class)());
    }

    private function assertListenersOfALazyContextAreWrappedOnce(?bool $supports): void
    {
        $request = new Request();
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener = new class($supports) extends AbstractListener {
            public int $calls = 0;

            public function __construct(private ?bool $supports)
            {
            }

            public function supports(Request $request): ?bool
            {
                return $this->supports;
            }

            public function authenticate(RequestEvent $event): void
            {
                ++$this->calls;
            }
        };
        $tokenStorage = new TokenStorage();
        $context = new LazyFirewallContext([$listener], null, null, null, $tokenStorage);
        $firewallMap = $this->createStub(FirewallMap::class);
        $firewallMap->method('getListeners')->willReturn([[$context], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator());

        for ($i = 1; $i <= 2; ++$i) {
            $firewall->reset();
            $firewall->onKernelRequest($event);
            $tokenStorage->getToken();

            $this->assertSame([$listener], \Closure::bind(fn () => $this->listeners, $context, FirewallContext::class)());
            $this->assertCount(1, $listeners = $firewall->getWrappedListeners());
            $this->assertSame($listener, $listeners[0]['stub']);
            $this->assertSame($i, $listener->calls);
        }
    }
}

abstract class DummyAuthenticator implements InteractiveAuthenticatorInterface
{
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
    }
}

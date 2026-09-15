<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;

class FirewallTest extends TestCase
{
    public function testOnKernelRequestRegistersExceptionListener()
    {
        $dispatcher = new EventDispatcher();

        $listener = $this->createMock(ExceptionListener::class);
        $listener
            ->expects($this->once())
            ->method('register')
            ->with($this->equalTo($dispatcher))
        ;

        $request = new Request();

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->willReturn([[], $listener, null])
        ;

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $firewall = new Firewall($map, $dispatcher);
        $firewall->onKernelRequest($event);
    }

    public function testDispatcherMustBeAbleToRegisterListeners()
    {
        $dispatcher = new class implements ContractsEventDispatcherInterface {
            public function dispatch(object $event, ?string $eventName = null): object
            {
                return $event;
            }
        };

        $this->expectException(\TypeError::class);

        new Firewall($this->createMock(FirewallMapInterface::class), $dispatcher);
    }

    public function testOnKernelRequestStopsWhenThereIsAResponse()
    {
        $called = [];

        $first = static function () use (&$called) {
            $called[] = 1;
        };

        $second = static function () use (&$called) {
            $called[] = 2;
        };

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->willReturn([[$first, $second], null, null])
        ;

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse(new Response());

        $firewall = new Firewall($map, new EventDispatcher());
        $firewall->onKernelRequest($event);

        $this->assertSame([1], $called);
    }

    public function testOnKernelRequestWithSubRequest()
    {
        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->never())
            ->method('getListeners')
        ;

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::SUB_REQUEST
        );

        $firewall = new Firewall($map, new EventDispatcher());
        $firewall->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testListenersAreCalled()
    {
        $calledListeners = [];

        $callableListener = static function () use (&$calledListeners) { $calledListeners[] = 'callableListener'; };
        $firewallListener = new class($calledListeners) implements FirewallListenerInterface {
            public function __construct(private array &$calledListeners)
            {
            }

            public function supports(Request $request): ?bool
            {
                return true;
            }

            public function authenticate(RequestEvent $event): void
            {
                $this->calledListeners[] = 'firewallListener';
            }

            public static function getPriority(): int
            {
                return 0;
            }
        };
        $callableFirewallListener = new class($calledListeners) extends AbstractListener {
            public function __construct(private array &$calledListeners)
            {
            }

            public function supports(Request $request): ?bool
            {
                return true;
            }

            public function authenticate(RequestEvent $event): void
            {
                $this->calledListeners[] = 'callableFirewallListener';
            }
        };

        $request = new Request();

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->willReturn([[$callableListener, $firewallListener, $callableFirewallListener], null, null])
        ;

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $firewall = new Firewall($map, new EventDispatcher());
        $firewall->onKernelRequest($event);

        $this->assertSame(['callableListener', 'firewallListener', 'callableFirewallListener'], $calledListeners);
    }
}

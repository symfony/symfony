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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;

class FirewallTest extends TestCase
{
    public function testOnKernelExceptionCallsTheExceptionListenerWithoutRegisteringIt()
    {
        $request = new Request();
        $exception = new \RuntimeException();

        $exceptionListener = $this->createMock(ExceptionListener::class);
        $exceptionListener
            ->expects($this->never())
            ->method('register')
        ;
        $exceptionListener
            ->expects($this->once())
            ->method('onKernelException')
            ->with($this->callback(static fn (ExceptionEvent $event) => $event->getThrowable() === $exception))
        ;

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->willReturn([[], $exceptionListener, null])
        ;

        $kernel = $this->createStub(HttpKernelInterface::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new Firewall($map));
        $listeners = $dispatcher->getListeners();

        $dispatcher->dispatch(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST), KernelEvents::REQUEST);

        $this->assertEquals($listeners, $dispatcher->getListeners());

        $dispatcher->dispatch(new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception), KernelEvents::EXCEPTION);
    }

    public function testOnKernelExceptionIsANoopOnceTheRequestIsFinished()
    {
        $request = new Request();

        $exceptionListener = $this->createMock(ExceptionListener::class);
        $exceptionListener
            ->expects($this->never())
            ->method('onKernelException')
        ;

        $map = $this->createStub(FirewallMapInterface::class);
        $map
            ->method('getListeners')
            ->willReturn([[], $exceptionListener, null])
        ;

        $kernel = $this->createStub(HttpKernelInterface::class);

        $firewall = new Firewall($map);
        $firewall->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $firewall->onKernelFinishRequest(new FinishRequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $firewall->onKernelException(new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new \RuntimeException()));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testPassingAnEventDispatcherIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/security-http 8.2: Passing an event dispatcher to "Symfony\Component\Security\Http\Firewall::__construct()" is deprecated, the argument will be removed in 9.0.');

        new Firewall($this->createStub(FirewallMapInterface::class), new EventDispatcher());
    }

    public function testOnKernelRequestStopsWhenThereIsAResponse()
    {
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

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->willReturn([[$listener, $listener], null, null])
        ;

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse(new Response());

        $firewall = new Firewall($map);
        $firewall->onKernelRequest($event);

        $this->assertSame(1, $listener->callCount);
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

        $firewall = new Firewall($map);
        $firewall->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testFirewallListenersAreCalled()
    {
        $calledListeners = [];

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
            ->willReturn([[$firewallListener, $callableFirewallListener], null, null])
        ;

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $firewall = new Firewall($map);
        $firewall->onKernelRequest($event);

        $this->assertSame(['firewallListener', 'callableFirewallListener'], $calledListeners);
    }
}

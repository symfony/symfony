<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Buz;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Qux;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ControllerAttributesController;

class ControllerAttributesListenerTest extends TestCase
{
    public function testOnKernelControllerArgumentsDispatchesEventsForEachAttribute()
    {
        $dispatchedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Qux::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });

        $listener = $this->createListener();
        $event = $this->createControllerArgumentsEvent('buzQuxAction');

        $listener->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $dispatcher);

        $this->assertSame([
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class,
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class,
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Qux::class,
        ], $dispatchedEvents);
    }

    public function testOnKernelResponseDispatchesEventsInReverseOrder()
    {
        $dispatchedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::RESPONSE.'.'.Buz::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });
        $dispatcher->addListener(KernelEvents::RESPONSE.'.'.Qux::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });

        $listener = $this->createListener();
        $event = $this->createResponseEvent('buzQuxAction');

        $listener->afterController($event, KernelEvents::RESPONSE, $dispatcher);

        $this->assertSame([
            KernelEvents::RESPONSE.'.'.Qux::class,
            KernelEvents::RESPONSE.'.'.Buz::class,
            KernelEvents::RESPONSE.'.'.Buz::class,
        ], $dispatchedEvents);
    }

    public function testOnKernelResponseDoesNothingWhenNoControllerEvent()
    {
        $dispatchedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::RESPONSE.'.'.Buz::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });

        $listener = $this->createListener();

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        );

        $listener->afterController($event, KernelEvents::RESPONSE, $dispatcher);

        $this->assertSame([], $dispatchedEvents);
    }

    public function testDispatchedEventIsTheSameInstance()
    {
        $capturedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class, static function (ControllerAttributeEvent $event) use (&$capturedEvents) {
            $capturedEvents[] = $event;
        });

        $listener = $this->createListener();
        $event = $this->createControllerArgumentsEvent('buzAction');

        $listener->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $dispatcher);

        $this->assertCount(2, $capturedEvents);
        $this->assertSame($event, $capturedEvents[0]->kernelEvent);
        $this->assertSame($event, $capturedEvents[1]->kernelEvent);
    }

    public function testClassLevelAttributesAreIncluded()
    {
        $dispatchedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });

        $listener = $this->createListener();
        $event = $this->createControllerArgumentsEvent('noAttributeAction');

        $listener->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $dispatcher);

        $this->assertSame([KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class], $dispatchedEvents);
    }

    public function testBeforeControllerDispatchesParentAttributeListeners()
    {
        $dispatchedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });

        $listener = $this->createListener([
            KernelEvents::CONTROLLER_ARGUMENTS => [
                Buz::class => true,
            ],
        ]);
        $event = $this->createControllerArgumentsEvent('subBuzAction');

        $listener->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $dispatcher);

        $this->assertSame([
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class,
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class,
        ], $dispatchedEvents);
    }

    public function testBeforeControllerSkipsWhenNoAttributeListenersAreRegistered()
    {
        $dispatchedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class, static function () use (&$dispatchedEvents) {
            $dispatchedEvents[] = true;
        });

        $listener = $this->createListener([]);
        $event = $this->createControllerArgumentsEvent('buzAction');

        $listener->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $dispatcher);

        $this->assertEmpty($dispatchedEvents);
    }

    public function testOnKernelControllerHandlesControllerChanges()
    {
        $dispatchedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER.'.'.Buz::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });
        $dispatcher->addListener(KernelEvents::CONTROLLER.'.'.Qux::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });

        $originalControllerSet = false;
        $dispatcher->addListener(KernelEvents::CONTROLLER.'.'.Buz::class, static function (ControllerAttributeEvent $event) use (&$originalControllerSet, &$dispatchedEvents) {
            if (!$originalControllerSet) {
                $event->kernelEvent->setController([new ControllerAttributesController(), 'buzQuxAction']);
                $originalControllerSet = true;
            }
        }, -1);

        $listener = $this->createListener();

        $event = new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            [new ControllerAttributesController(), 'buzAction'],
            new Request(),
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener->beforeController($event, KernelEvents::CONTROLLER, $dispatcher);

        $this->assertContains(KernelEvents::CONTROLLER.'.'.Qux::class, $dispatchedEvents);
    }

    public function testOnKernelControllerArgumentsHandlesControllerChanges()
    {
        $dispatchedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Qux::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });

        $listener = $this->createListener();
        $event = $this->createControllerArgumentsEvent('buzAction');

        $originalControllerSet = false;
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Buz::class, static function (ControllerAttributeEvent $event) use (&$originalControllerSet, &$dispatchedEvents) {
            if (!$originalControllerSet) {
                $event->kernelEvent->setController([new ControllerAttributesController(), 'buzQuxAction']);
                $originalControllerSet = true;
            }
        }, -1);

        $listener->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $dispatcher);

        $this->assertContains(KernelEvents::CONTROLLER_ARGUMENTS.'.'.Qux::class, $dispatchedEvents);
    }

    private function createListener(?array $attributesWithListenersByEvent = null): ControllerAttributesListener
    {
        return new ControllerAttributesListener($attributesWithListenersByEvent ?? [
            KernelEvents::CONTROLLER => [
                Buz::class => true,
                Qux::class => true,
            ],
            KernelEvents::CONTROLLER_ARGUMENTS => [
                Buz::class => true,
                Qux::class => true,
            ],
            KernelEvents::VIEW => [
                Buz::class => true,
                Qux::class => true,
            ],
            KernelEvents::RESPONSE => [
                Buz::class => true,
                Qux::class => true,
            ],
            KernelEvents::FINISH_REQUEST => [
                Buz::class => true,
                Qux::class => true,
            ],
        ]);
    }

    private function createControllerArgumentsEvent(string $method): ControllerArgumentsEvent
    {
        return new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            [new ControllerAttributesController(), $method],
            [],
            new Request(),
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    private function createResponseEvent(string $method): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $controller = [new ControllerAttributesController(), $method];
        $controllerEvent = new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, $controllerEvent, [], $request, HttpKernelInterface::MAIN_REQUEST);
        $controllerMetadata = new ControllerArgumentsMetadata($controllerEvent, $controllerArgumentsEvent);

        return new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
            $controllerMetadata
        );
    }
}

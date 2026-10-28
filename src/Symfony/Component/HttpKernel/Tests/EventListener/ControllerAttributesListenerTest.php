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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RecoverableAttribute;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Bar;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Buz;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Counted;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Qux;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ControllerAttributesController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\CountedAttributeController;

class ControllerAttributesListenerTest extends TestCase
{
    protected function setUp(): void
    {
        (new \ReflectionProperty(ControllerAttributesListener::class, 'attributesCache'))->setValue(null, []);
    }

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

    public function testDefaultExpressionLanguageAllowsAnyConstant()
    {
        $result = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER.'.'.Buz::class, static function (ControllerAttributeEvent $event) use (&$result) {
            $result = $event->evaluate(new Expression('constant("PHP_VERSION")'));
        });

        $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), [new ControllerAttributesController(), 'buzAction'], new Request(), HttpKernelInterface::MAIN_REQUEST);
        $this->createListener()->beforeController($event, KernelEvents::CONTROLLER, $dispatcher);

        $this->assertSame(\PHP_VERSION, $result);
    }

    #[DataProvider('provideControllers')]
    public function testAttributesAreInstantiatedOncePerController(\Closure $createController)
    {
        $listener = $this->createListener([KernelEvents::CONTROLLER => [Counted::class => true]]);
        Counted::$instances = 0;

        for ($i = 0; $i < 3; ++$i) {
            $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), $createController(), new Request(), HttpKernelInterface::MAIN_REQUEST);
            $listener->beforeController($event, KernelEvents::CONTROLLER, new EventDispatcher());

            $this->assertSame(['class', 'method'], array_map(static fn (Counted $attribute) => $attribute->value, $event->getAttributes('*')));
        }

        $this->assertSame(2, Counted::$instances);
    }

    public static function provideControllers(): iterable
    {
        yield 'method' => [static fn () => [new CountedAttributeController(), 'action']];
        yield 'invokable' => [static fn () => new CountedAttributeController()];
        yield 'first-class callable' => [static fn () => (new CountedAttributeController())->action(...)];
    }

    public function testListenersShareTheirCache()
    {
        Counted::$instances = 0;

        foreach ([$this->createListener([]), $this->createListener([])] as $listener) {
            $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), [new CountedAttributeController(), 'action'], new Request(), HttpKernelInterface::MAIN_REQUEST);
            $listener->beforeController($event, KernelEvents::CONTROLLER, new EventDispatcher());

            $this->assertSame(['class', 'method'], array_map(static fn (Counted $attribute) => $attribute->value, $event->getAttributes('*')));
        }

        $this->assertSame(2, Counted::$instances);
    }

    public function testEachRequestGetsItsOwnAttributeInstances()
    {
        $values = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER.'.'.Counted::class, static function (ControllerAttributeEvent $event) use (&$values) {
            $values[] = $event->attribute->value;
            $event->attribute->value = 'changed';
        });

        $listener = $this->createListener([KernelEvents::CONTROLLER => [Counted::class => true]]);
        $attributes = [];

        for ($i = 0; $i < 2; ++$i) {
            $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), [new CountedAttributeController(), 'action'], new Request(), HttpKernelInterface::MAIN_REQUEST);
            $listener->beforeController($event, KernelEvents::CONTROLLER, $dispatcher);
            $attributes[] = $event->getAttributes('*');
        }

        $this->assertSame(['class', 'method', 'class', 'method'], $values);
        $this->assertNotSame($attributes[0][0], $attributes[1][0]);
        $this->assertNotSame($attributes[0][1], $attributes[1][1]);
    }

    public function testClosuresGetTheirOwnAttributes()
    {
        $listener = $this->createListener([]);
        $attributes = [];

        foreach ([#[Bar('a')] static fn () => null, #[Bar('b')] static fn () => null] as $closure) {
            foreach ([$closure, [$closure, '__invoke']] as $controller) {
                $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), $controller, new Request(), HttpKernelInterface::MAIN_REQUEST);
                $listener->beforeController($event, KernelEvents::CONTROLLER, new EventDispatcher());
                $attributes[] = $event->getAttributes('*');
            }
        }

        $this->assertEquals([[new Bar('a')], [new Bar('a')], [new Bar('b')], [new Bar('b')]], $attributes);
    }

    public function testAttributesPassedWithTheControllerDoNotReplaceTheDeclaredOnes()
    {
        $dispatchedEvents = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER.'.'.Buz::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });
        $dispatcher->addListener(KernelEvents::CONTROLLER.'.'.Qux::class, static function ($event, $name) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $name;
        });

        $listener = $this->createListener();

        $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), static fn () => null, new Request(), HttpKernelInterface::MAIN_REQUEST);
        $event->setController([new ControllerAttributesController(), 'buzAction'], [new Qux()]);
        $listener->beforeController($event, KernelEvents::CONTROLLER, $dispatcher);

        $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), [new ControllerAttributesController(), 'buzAction'], new Request(), HttpKernelInterface::MAIN_REQUEST);
        $listener->beforeController($event, KernelEvents::CONTROLLER, $dispatcher);

        $this->assertSame([
            KernelEvents::CONTROLLER.'.'.Qux::class,
            KernelEvents::CONTROLLER.'.'.Buz::class,
            KernelEvents::CONTROLLER.'.'.Buz::class,
        ], $dispatchedEvents);
    }

    public function testFailureOfARecoverableAttributeIsPassedToTheControllerParameterTypedToReceiveIt()
    {
        $failure = new \DomainException();
        $event = $this->createGuardedArgumentsEvent(#[RecoverableGuard] static fn (\RangeException|\DomainException|null $exception = null) => null);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $this->createFailingDispatcher(KernelEvents::CONTROLLER_ARGUMENTS, $failure));

        $this->assertSame([$failure], $event->getArguments());
    }

    public function testFirstRecoverableExceptionOfTheChainIsPassed()
    {
        $previous = new \DomainException();
        $event = $this->createGuardedArgumentsEvent(#[RecoverableGuard] static fn (?\DomainException $exception = null) => null);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $this->createFailingDispatcher(KernelEvents::CONTROLLER_ARGUMENTS, new \RuntimeException('', 0, $previous)));

        $this->assertSame([$previous], $event->getArguments());
    }

    public function testFailureIsThrownWhenTheParameterTypeIsBroaderThanTheRecoverableExceptions()
    {
        $failure = new \DomainException();
        $event = $this->createGuardedArgumentsEvent(#[RecoverableGuard] static fn (?\Exception $exception = null) => null);

        $this->expectExceptionObject($failure);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $this->createFailingDispatcher(KernelEvents::CONTROLLER_ARGUMENTS, $failure));
    }

    public function testFailureBeforeTheArgumentsAreResolvedIsThrownWhenTheParameterTypeIsBroaderThanTheRecoverableExceptions()
    {
        $failure = new \DomainException();
        $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), #[RecoverableGuard] static fn (?\Exception $exception = null) => null, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->expectExceptionObject($failure);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER, $this->createFailingDispatcher(KernelEvents::CONTROLLER, $failure));
    }

    public function testFailureIsThrownWhenNoParameterIsTypedToReceiveIt()
    {
        $failure = new \DomainException();
        $event = $this->createGuardedArgumentsEvent(#[RecoverableGuard] static fn (?\RangeException $exception = null) => null);

        $this->expectExceptionObject($failure);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $this->createFailingDispatcher(KernelEvents::CONTROLLER_ARGUMENTS, $failure));
    }

    public function testAttributeExtendingARecoverableOneIsRecoverable()
    {
        $failure = new \DomainException();
        $event = $this->createGuardedArgumentsEvent(#[ChildRecoverableGuard] static fn (?\DomainException $exception = null) => null);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $this->createFailingDispatcher(KernelEvents::CONTROLLER_ARGUMENTS, $failure));

        $this->assertSame([$failure], $event->getArguments());
    }

    public function testFailureOfANonRecoverableAttributeIsThrown()
    {
        $failure = new \DomainException();
        $event = $this->createGuardedArgumentsEvent(#[Guard] static fn (?\DomainException $exception = null) => null);

        $this->expectExceptionObject($failure);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $this->createFailingDispatcher(KernelEvents::CONTROLLER_ARGUMENTS, $failure));
    }

    public function testFailureNotDeclaredAsRecoverableIsThrown()
    {
        $failure = new \RuntimeException();
        $event = $this->createGuardedArgumentsEvent(#[RecoverableGuard] static fn (?\Throwable $exception = null) => null);

        $this->expectExceptionObject($failure);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $this->createFailingDispatcher(KernelEvents::CONTROLLER_ARGUMENTS, $failure));
    }

    public function testFailureIsThrownOnceTheParameterReceivedAnotherOne()
    {
        $second = new \DomainException();
        $event = $this->createGuardedArgumentsEvent(#[RecoverableGuard, RecoverableGuard] static fn (?\DomainException $exception = null) => null);

        $this->expectExceptionObject($second);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, $this->createFailingDispatcher(KernelEvents::CONTROLLER_ARGUMENTS, new \DomainException(), $second));
    }

    public function testFailureBeforeTheArgumentsAreResolvedIsPassedOnceTheyAre()
    {
        $failure = new \DomainException();
        $controller = #[RecoverableGuard] static fn (?\DomainException $exception = null) => null;
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $listener = $this->createGuardListener();

        $listener->beforeController(new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST), KernelEvents::CONTROLLER, $this->createFailingDispatcher(KernelEvents::CONTROLLER, $failure));

        $event = new ControllerArgumentsEvent($kernel, $controller, [null], $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->beforeController($event, KernelEvents::CONTROLLER_ARGUMENTS, new EventDispatcher());

        $this->assertSame([$failure], $event->getArguments());
    }

    public function testFailureBeforeTheArgumentsAreResolvedIsThrownWhenNoParameterIsTypedToReceiveIt()
    {
        $failure = new \DomainException();
        $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), #[RecoverableGuard] static fn (?\RangeException $exception = null) => null, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->expectExceptionObject($failure);

        $this->createGuardListener()->beforeController($event, KernelEvents::CONTROLLER, $this->createFailingDispatcher(KernelEvents::CONTROLLER, $failure));
    }

    private function createGuardListener(): ControllerAttributesListener
    {
        $guards = [RecoverableGuard::class => true, Guard::class => true];

        return new ControllerAttributesListener([KernelEvents::CONTROLLER => $guards, KernelEvents::CONTROLLER_ARGUMENTS => $guards]);
    }

    private function createGuardedArgumentsEvent(\Closure $controller): ControllerArgumentsEvent
    {
        return new ControllerArgumentsEvent($this->createStub(HttpKernelInterface::class), $controller, [null], new Request(), HttpKernelInterface::MAIN_REQUEST);
    }

    /**
     * Each guard dispatched throws the next failure.
     */
    private function createFailingDispatcher(string $eventName, \Throwable ...$failures): EventDispatcher
    {
        $fail = static function () use (&$failures) {
            throw array_shift($failures);
        };

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener($eventName.'.'.RecoverableGuard::class, $fail);
        $dispatcher->addListener($eventName.'.'.Guard::class, $fail);

        return $dispatcher;
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

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_FUNCTION)]
#[RecoverableAttribute(\DomainException::class)]
class RecoverableGuard
{
}

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class ChildRecoverableGuard extends RecoverableGuard
{
}

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class Guard
{
}

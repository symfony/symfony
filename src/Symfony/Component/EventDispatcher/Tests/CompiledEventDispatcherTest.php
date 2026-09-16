<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\CompiledEventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\ScopedEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

class CompiledEventDispatcherTest extends TestCase
{
    private const preFoo = 'pre.foo';
    private const postFoo = 'post.foo';

    public function testListenersRunInTheOrderOfTheMap()
    {
        $called = [];
        $dispatcher = $this->createDispatcher([
            self::preFoo => [10 => [['high', 'onEvent']], 0 => [['first', 'onEvent'], ['second', 'onEvent']]],
        ], $called);

        $dispatcher->dispatch(new Event(), self::preFoo);

        $this->assertSame(['high', 'first', 'second'], $called);
    }

    public function testAnEventWithoutListenersDispatches()
    {
        $called = [];
        $dispatcher = $this->createDispatcher([self::preFoo => [0 => [['foo', 'onEvent']]]], $called);

        $event = new Event();

        $this->assertSame($event, $dispatcher->dispatch($event, self::postFoo));
        $this->assertSame([], $called);
    }

    public function testAListenerIsFetchedWhenItIsAboutToRun()
    {
        $fetched = [];
        $called = [];
        $dispatcher = $this->createDispatcher([
            self::preFoo => [0 => [['first', 'stopPropagation'], ['second', 'onEvent']]],
            self::postFoo => [0 => [['third', 'onEvent']]],
        ], $called, $fetched);

        $this->assertSame([], $fetched);

        $dispatcher->dispatch(new Event(), self::preFoo);

        $this->assertSame(['first'], $fetched, 'the listener that propagation never reached is not fetched, and neither is the one of another event');
        $this->assertSame(['first'], $called);
    }

    public function testHasListenersDoesNotFetchAnything()
    {
        $fetched = [];
        $called = [];
        $dispatcher = $this->createDispatcher([self::preFoo => [0 => [['foo', 'onEvent']]]], $called, $fetched);

        $this->assertTrue($dispatcher->hasListeners());
        $this->assertTrue($dispatcher->hasListeners(self::preFoo));
        $this->assertFalse($dispatcher->hasListeners(self::postFoo));
        $this->assertSame([], $fetched);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testAddingAListenerKeepsTheCompiledOnes()
    {
        $called = [];
        $dispatcher = $this->createDispatcher([self::preFoo => [0 => [['compiled', 'onEvent']]]], $called);

        $dispatcher->addListener(self::preFoo, static function () use (&$called) { $called[] = 'added'; }, -10);
        $dispatcher->dispatch(new Event(), self::preFoo);

        $this->assertSame(['compiled', 'added'], $called);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testRemovingACompiledListener()
    {
        $called = [];
        $dispatcher = $this->createDispatcher([self::preFoo => [0 => [['foo', 'onEvent'], ['bar', 'onEvent']]]], $called);

        $listeners = $dispatcher->getListeners(self::preFoo);

        $this->assertCount(2, $listeners);
        $this->assertSame(0, $dispatcher->getListenerPriority(self::preFoo, $listeners[0]));

        $dispatcher->removeListener(self::preFoo, $listeners[0]);
        $dispatcher->dispatch(new Event(), self::preFoo);

        $this->assertSame(['bar'], $called);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSubscribersRunNextToTheCompiledListeners()
    {
        $called = [];
        $dispatcher = $this->createDispatcher([self::preFoo => [0 => [['compiled', 'onEvent']]]], $called);
        $subscriber = new class($called) implements EventSubscriberInterface {
            public function __construct(private array &$called)
            {
            }

            public function onPreFoo(): void
            {
                $this->called[] = 'subscriber';
            }

            public static function getSubscribedEvents(): array
            {
                return ['pre.foo' => ['onPreFoo', 100]];
            }
        };

        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch(new Event(), self::preFoo);

        $this->assertSame(['subscriber', 'compiled'], $called);

        $called = [];
        $dispatcher->removeSubscriber($subscriber);
        $dispatcher->dispatch(new Event(), self::preFoo);

        $this->assertSame(['compiled'], $called);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testMutatingTheDispatcherIsDeprecated()
    {
        $called = [];
        $dispatcher = $this->createDispatcher([], $called);
        $listener = static function () {};
        $subscriber = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [];
            }
        };

        foreach ([
            'addListener' => [self::preFoo, $listener],
            'addSubscriber' => [$subscriber],
            'removeListener' => [self::preFoo, $listener],
            'removeSubscriber' => [$subscriber],
        ] as $method => $arguments) {
            $deprecations = [];
            set_error_handler(static function (int $type, string $message) use (&$deprecations) {
                $deprecations[] = $message;

                return true;
            }, \E_USER_DEPRECATED);

            try {
                $dispatcher->$method(...$arguments);
            } finally {
                restore_error_handler();
            }

            $this->assertCount(1, $deprecations);
            $this->assertStringStartsWith(\sprintf('Since symfony/event-dispatcher 8.2: Calling "%s::%s()" is deprecated, ', CompiledEventDispatcher::class, $method), $deprecations[0]);
            $this->assertStringEndsWith(\sprintf('a "%s" wrapping this one instead.', ScopedEventDispatcher::class), $deprecations[0]);
        }
    }

    private function createDispatcher(array $listeners, array &$called, array &$fetched = []): CompiledEventDispatcher
    {
        $locator = new class($called, $fetched) implements ContainerInterface {
            public function __construct(private array &$called, private array &$fetched)
            {
            }

            public function get(string $id): object
            {
                $this->fetched[] = $id;
                $called = &$this->called;

                return new class($id, $called) {
                    public function __construct(private string $id, private array &$called)
                    {
                    }

                    public function onEvent(): void
                    {
                        $this->called[] = $this->id;
                    }

                    public function stopPropagation(Event $event): void
                    {
                        $this->called[] = $this->id;
                        $event->stopPropagation();
                    }
                };
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        return new CompiledEventDispatcher($listeners, $locator);
    }
}

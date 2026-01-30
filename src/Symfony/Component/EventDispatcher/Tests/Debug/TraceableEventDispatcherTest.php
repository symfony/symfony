<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\Event;

class TraceableEventDispatcherTest extends TestCase
{
    public function testAddRemoveListener(): void
    {
        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());

        $tdispatcher->addListener('foo', $listener = static function (): void {});
        $listeners = $dispatcher->getListeners('foo');
        $this->assertCount(1, $listeners);
        $this->assertSame($listener, $listeners[0]);

        $tdispatcher->removeListener('foo', $listener);
        $this->assertCount(0, $dispatcher->getListeners('foo'));
    }

    public function testGetListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());

        $tdispatcher->addListener('foo', $listener = static function (): void {});
        $this->assertSame($dispatcher->getListeners('foo'), $tdispatcher->getListeners('foo'));
    }

    public function testHasListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());

        $this->assertFalse($dispatcher->hasListeners('foo'));
        $this->assertFalse($tdispatcher->hasListeners('foo'));

        $tdispatcher->addListener('foo', $listener = static function (): void {});
        $this->assertTrue($dispatcher->hasListeners('foo'));
        $this->assertTrue($tdispatcher->hasListeners('foo'));
    }

    public function testGetListenerPriority(): void
    {
        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());

        $tdispatcher->addListener('foo', static function (): void {}, 123);

        $listeners = $dispatcher->getListeners('foo');
        $this->assertSame(123, $tdispatcher->getListenerPriority('foo', $listeners[0]));

        // Verify that priority is preserved when listener is removed and re-added
        // in preProcess() and postProcess().
        $tdispatcher->dispatch(new Event(), 'foo');
        $listeners = $dispatcher->getListeners('foo');
        $this->assertSame(123, $tdispatcher->getListenerPriority('foo', $listeners[0]));
    }

    public function testGetListenerPriorityWhileDispatching(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $priorityWhileDispatching = null;

        $listener = static function () use ($tdispatcher, &$priorityWhileDispatching, &$listener): void {
            $priorityWhileDispatching = $tdispatcher->getListenerPriority('bar', $listener);
        };

        $tdispatcher->addListener('bar', $listener, 5);
        $tdispatcher->dispatch(new Event(), 'bar');
        $this->assertSame(5, $priorityWhileDispatching);
    }

    public function testAddRemoveSubscriber(): void
    {
        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());

        $subscriber = new EventSubscriber();

        $tdispatcher->addSubscriber($subscriber);
        $listeners = $dispatcher->getListeners('foo');
        $this->assertCount(1, $listeners);
        $this->assertSame([$subscriber, 'call'], $listeners[0]);

        $tdispatcher->removeSubscriber($subscriber);
        $this->assertCount(0, $dispatcher->getListeners('foo'));
    }

    public function testGetCalledListeners(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->addListener('foo', static function (): void {}, 5);

        $listeners = $tdispatcher->getNotCalledListeners();
        $this->assertArrayHasKey('stub', $listeners[0]);
        unset($listeners[0]['stub']);
        $this->assertEquals([], $tdispatcher->getCalledListeners());
        $this->assertEquals([['event' => 'foo', 'pretty' => 'closure', 'priority' => 5]], $listeners);

        $tdispatcher->dispatch(new Event(), 'foo');

        $listeners = $tdispatcher->getCalledListeners();
        $this->assertArrayHasKey('stub', $listeners[0]);
        unset($listeners[0]['stub']);
        $this->assertEquals([['event' => 'foo', 'pretty' => 'closure', 'priority' => 5]], $listeners);
        $this->assertEquals([], $tdispatcher->getNotCalledListeners());
    }

    public function testGetNotCalledClosureListeners(): void
    {
        $instantiationCount = 0;

        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->addListener('foo', [static function () use (&$instantiationCount): void { ++$instantiationCount; }, 'onFoo']);

        $tdispatcher->getNotCalledListeners();

        $this->assertSame(0, $instantiationCount);
    }

    public function testClearCalledListeners(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->addListener('foo', static function (): void {}, 5);

        $tdispatcher->dispatch(new Event(), 'foo');
        $tdispatcher->reset();

        $listeners = $tdispatcher->getNotCalledListeners();
        $this->assertArrayHasKey('stub', $listeners[0]);
        unset($listeners[0]['stub']);
        $this->assertEquals([], $tdispatcher->getCalledListeners());
        $this->assertEquals([['event' => 'foo', 'pretty' => 'closure', 'priority' => 5]], $listeners);
    }

    public function testDispatchAfterReset(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->addListener('foo', static function (): void {}, 5);

        $tdispatcher->reset();
        $tdispatcher->dispatch(new Event(), 'foo');

        $listeners = $tdispatcher->getCalledListeners();
        $this->assertArrayHasKey('stub', $listeners[0]);
    }

    public function testGetCalledListenersNested(): void
    {
        $tdispatcher = null;
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('foo', static function (Event $event, $eventName, $dispatcher) use (&$tdispatcher): void {
            $tdispatcher = $dispatcher;
            $dispatcher->dispatch(new Event(), 'bar');
        });
        $dispatcher->addListener('bar', static function (Event $event): void {});
        $dispatcher->dispatch(new Event(), 'foo');
        $this->assertSame($dispatcher, $tdispatcher);
        $this->assertCount(2, $dispatcher->getCalledListeners());
    }

    public function testItReturnsNoOrphanedEventsWhenCreated(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $events = $tdispatcher->getOrphanedEvents();
        $this->assertSame([], $events);
    }

    public function testItReturnsOrphanedEventsAfterDispatch(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->dispatch(new Event(), 'foo');
        $events = $tdispatcher->getOrphanedEvents();
        $this->assertCount(1, $events);
        $this->assertEquals(['foo'], $events);
    }

    public function testItDoesNotReturnHandledEvents(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->addListener('foo', static function (): void {});
        $tdispatcher->dispatch(new Event(), 'foo');
        $events = $tdispatcher->getOrphanedEvents();
        $this->assertSame([], $events);
    }

    public function testLogger(): void
    {
        $logger = new BufferingLogger();

        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch(), $logger);
        $tdispatcher->addListener('foo', $listener1 = static function (): void {});
        $tdispatcher->addListener('foo', $listener2 = static function (): void {});

        $tdispatcher->dispatch(new Event(), 'foo');

        $this->assertSame([
            [
                'debug',
                'Notified event "{event}" to listener "{listener}".',
                ['event' => 'foo', 'listener' => 'closure'],
            ],
            [
                'debug',
                'Notified event "{event}" to listener "{listener}".',
                ['event' => 'foo', 'listener' => 'closure'],
            ],
        ], $logger->cleanLogs());
    }

    public function testLoggerWithStoppedEvent(): void
    {
        $logger = new BufferingLogger();

        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch(), $logger);
        $tdispatcher->addListener('foo', $listener1 = static function (Event $event): void { $event->stopPropagation(); });
        $tdispatcher->addListener('foo', $listener2 = static function (): void {});

        $tdispatcher->dispatch(new Event(), 'foo');

        $this->assertSame([
            [
                'debug',
                'Notified event "{event}" to listener "{listener}".',
                ['event' => 'foo', 'listener' => 'closure'],
            ],
            [
                'debug',
                'Listener "{listener}" stopped propagation of the event "{event}".',
                ['event' => 'foo', 'listener' => 'closure'],
            ],
            [
                'debug',
                'Listener "{listener}" was not called for event "{event}".',
                ['event' => 'foo', 'listener' => 'closure'],
            ],
        ], $logger->cleanLogs());
    }

    public function testDispatchCallListeners(): void
    {
        $called = [];

        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());
        $tdispatcher->addListener('foo', static function () use (&$called): void { $called[] = 'foo1'; }, 10);
        $tdispatcher->addListener('foo', static function () use (&$called): void { $called[] = 'foo2'; }, 20);

        $tdispatcher->dispatch(new Event(), 'foo');

        $this->assertSame(['foo2', 'foo1'], $called);
    }

    public function testDispatchNested(): void
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $loop = 1;
        $dispatchedEvents = 0;
        $dispatcher->addListener('foo', $listener1 = static function () use ($dispatcher, &$loop): void {
            ++$loop;
            if (2 == $loop) {
                $dispatcher->dispatch(new Event(), 'foo');
            }
        });
        $dispatcher->addListener('foo', static function () use (&$dispatchedEvents): void {
            ++$dispatchedEvents;
        });

        $dispatcher->dispatch(new Event(), 'foo');

        $this->assertSame(2, $dispatchedEvents);
    }

    public function testDispatchReusedEventNested(): void
    {
        $nestedCall = false;
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('foo', static function (Event $e) use ($dispatcher): void {
            $dispatcher->dispatch(new Event(), 'bar', $e);
        });
        $dispatcher->addListener('bar', static function (Event $e) use (&$nestedCall): void {
            $nestedCall = true;
        });

        $this->assertFalse($nestedCall);
        $dispatcher->dispatch(new Event(), 'foo');
        $this->assertTrue($nestedCall);
    }

    public function testListenerCanRemoveItselfWhenExecuted(): void
    {
        $eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $listener1 = static function ($event, $eventName, EventDispatcherInterface $dispatcher) use (&$listener1): void {
            $dispatcher->removeListener('foo', $listener1);
        };
        $eventDispatcher->addListener('foo', $listener1);
        $eventDispatcher->addListener('foo', static function (): void {});
        $eventDispatcher->dispatch(new Event(), 'foo');

        $this->assertCount(1, $eventDispatcher->getListeners('foo'), 'expected listener1 to be removed');
    }

    public function testClearOrphanedEvents(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->dispatch(new Event(), 'foo');
        $events = $tdispatcher->getOrphanedEvents();
        $this->assertCount(1, $events);
        $tdispatcher->reset();
        $events = $tdispatcher->getOrphanedEvents();
        $this->assertCount(0, $events);
    }
}

class EventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['foo' => 'call'];
    }
}

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
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableEventDispatcherTest extends TestCase
{
    public function testAddRemoveListener(): void
    {
        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());

        $tdispatcher->addListener('foo', $listener = function (): void {});
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

        $tdispatcher->addListener('foo', $listener = function (): void {});
        $this->assertSame($dispatcher->getListeners('foo'), $tdispatcher->getListeners('foo'));
    }

    public function testHasListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());

        $this->assertFalse($dispatcher->hasListeners('foo'));
        $this->assertFalse($tdispatcher->hasListeners('foo'));

        $tdispatcher->addListener('foo', $listener = function (): void {});
        $this->assertTrue($dispatcher->hasListeners('foo'));
        $this->assertTrue($tdispatcher->hasListeners('foo'));
    }

    public function testGetListenerPriority(): void
    {
        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());

        $tdispatcher->addListener('foo', function (): void {}, 123);

        $listeners = $dispatcher->getListeners('foo');
        $this->assertSame(123, $tdispatcher->getListenerPriority('foo', $listeners[0]));

        // Verify that priority is preserved when listener is removed and re-added
        // in preProcess() and postProcess().
        $tdispatcher->dispatch('foo', new Event());
        $listeners = $dispatcher->getListeners('foo');
        $this->assertSame(123, $tdispatcher->getListenerPriority('foo', $listeners[0]));
    }

    public function testGetListenerPriorityWhileDispatching(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $priorityWhileDispatching = null;

        $listener = function () use ($tdispatcher, &$priorityWhileDispatching, &$listener): void {
            $priorityWhileDispatching = $tdispatcher->getListenerPriority('bar', $listener);
        };

        $tdispatcher->addListener('bar', $listener, 5);
        $tdispatcher->dispatch('bar');
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
        $this->assertSame(array($subscriber, 'call'), $listeners[0]);

        $tdispatcher->removeSubscriber($subscriber);
        $this->assertCount(0, $dispatcher->getListeners('foo'));
    }

    public function testGetCalledListeners(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->addListener('foo', function (): void {}, 5);

        $listeners = $tdispatcher->getNotCalledListeners();
        $this->assertArrayHasKey('stub', $listeners['foo.closure']);
        unset($listeners['foo.closure']['stub']);
        $this->assertEquals(array(), $tdispatcher->getCalledListeners());
        $this->assertEquals(array('foo.closure' => array('event' => 'foo', 'pretty' => 'closure', 'priority' => 5)), $listeners);

        $tdispatcher->dispatch('foo');

        $listeners = $tdispatcher->getCalledListeners();
        $this->assertArrayHasKey('stub', $listeners['foo.closure']);
        unset($listeners['foo.closure']['stub']);
        $this->assertEquals(array('foo.closure' => array('event' => 'foo', 'pretty' => 'closure', 'priority' => 5)), $listeners);
        $this->assertEquals(array(), $tdispatcher->getNotCalledListeners());
    }

    public function testClearCalledListeners(): void
    {
        $tdispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $tdispatcher->addListener('foo', function (): void {}, 5);

        $tdispatcher->dispatch('foo');
        $tdispatcher->reset();

        $listeners = $tdispatcher->getNotCalledListeners();
        $this->assertArrayHasKey('stub', $listeners['foo.closure']);
        unset($listeners['foo.closure']['stub']);
        $this->assertEquals(array(), $tdispatcher->getCalledListeners());
        $this->assertEquals(array('foo.closure' => array('event' => 'foo', 'pretty' => 'closure', 'priority' => 5)), $listeners);
    }

    public function testGetCalledListenersNested(): void
    {
        $tdispatcher = null;
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('foo', function (Event $event, $eventName, $dispatcher) use (&$tdispatcher): void {
            $tdispatcher = $dispatcher;
            $dispatcher->dispatch('bar');
        });
        $dispatcher->addListener('bar', function (Event $event): void {});
        $dispatcher->dispatch('foo');
        $this->assertSame($dispatcher, $tdispatcher);
        $this->assertCount(2, $dispatcher->getCalledListeners());
    }

    public function testLogger(): void
    {
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch(), $logger);
        $tdispatcher->addListener('foo', $listener1 = function (): void {});
        $tdispatcher->addListener('foo', $listener2 = function (): void {});

        $logger->expects($this->at(0))->method('debug')->with('Notified event "{event}" to listener "{listener}".', array('event' => 'foo', 'listener' => 'closure'));
        $logger->expects($this->at(1))->method('debug')->with('Notified event "{event}" to listener "{listener}".', array('event' => 'foo', 'listener' => 'closure'));

        $tdispatcher->dispatch('foo');
    }

    public function testLoggerWithStoppedEvent(): void
    {
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch(), $logger);
        $tdispatcher->addListener('foo', $listener1 = function (Event $event): void { $event->stopPropagation(); });
        $tdispatcher->addListener('foo', $listener2 = function (): void {});

        $logger->expects($this->at(0))->method('debug')->with('Notified event "{event}" to listener "{listener}".', array('event' => 'foo', 'listener' => 'closure'));
        $logger->expects($this->at(1))->method('debug')->with('Listener "{listener}" stopped propagation of the event "{event}".', array('event' => 'foo', 'listener' => 'closure'));
        $logger->expects($this->at(2))->method('debug')->with('Listener "{listener}" was not called for event "{event}".', array('event' => 'foo', 'listener' => 'closure'));

        $tdispatcher->dispatch('foo');
    }

    public function testDispatchCallListeners(): void
    {
        $called = array();

        $dispatcher = new EventDispatcher();
        $tdispatcher = new TraceableEventDispatcher($dispatcher, new Stopwatch());
        $tdispatcher->addListener('foo', function () use (&$called): void { $called[] = 'foo1'; }, 10);
        $tdispatcher->addListener('foo', function () use (&$called): void { $called[] = 'foo2'; }, 20);

        $tdispatcher->dispatch('foo');

        $this->assertSame(array('foo2', 'foo1'), $called);
    }

    public function testDispatchNested(): void
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $loop = 1;
        $dispatchedEvents = 0;
        $dispatcher->addListener('foo', $listener1 = function () use ($dispatcher, &$loop): void {
            ++$loop;
            if (2 == $loop) {
                $dispatcher->dispatch('foo');
            }
        });
        $dispatcher->addListener('foo', function () use (&$dispatchedEvents): void {
            ++$dispatchedEvents;
        });

        $dispatcher->dispatch('foo');

        $this->assertSame(2, $dispatchedEvents);
    }

    public function testDispatchReusedEventNested(): void
    {
        $nestedCall = false;
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('foo', function (Event $e) use ($dispatcher): void {
            $dispatcher->dispatch('bar', $e);
        });
        $dispatcher->addListener('bar', function (Event $e) use (&$nestedCall): void {
            $nestedCall = true;
        });

        $this->assertFalse($nestedCall);
        $dispatcher->dispatch('foo');
        $this->assertTrue($nestedCall);
    }

    public function testListenerCanRemoveItselfWhenExecuted(): void
    {
        $eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $listener1 = function ($event, $eventName, EventDispatcherInterface $dispatcher) use (&$listener1): void {
            $dispatcher->removeListener('foo', $listener1);
        };
        $eventDispatcher->addListener('foo', $listener1);
        $eventDispatcher->addListener('foo', function (): void {});
        $eventDispatcher->dispatch('foo');

        $this->assertCount(1, $eventDispatcher->getListeners('foo'), 'expected listener1 to be removed');
    }
}

class EventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array('foo' => 'call');
    }
}

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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

class EventDispatcherTest extends TestCase
{
    /* Some pseudo events */
    private const preFoo = 'pre.foo';
    private const postFoo = 'post.foo';
    private const preBar = 'pre.bar';

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    private $listener;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createEventDispatcher();
        $this->listener = new TestEventListener();
    }

    protected function tearDown(): void
    {
        $this->dispatcher = null;
        $this->listener = null;
    }

    protected function createEventDispatcher()
    {
        return new EventDispatcher();
    }

    public function testInitialState()
    {
        self::assertEquals([], $this->dispatcher->getListeners());
        self::assertFalse($this->dispatcher->hasListeners(self::preFoo));
        self::assertFalse($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testAddListener()
    {
        $this->dispatcher->addListener('pre.foo', [$this->listener, 'preFoo']);
        $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo']);
        self::assertTrue($this->dispatcher->hasListeners());
        self::assertTrue($this->dispatcher->hasListeners(self::preFoo));
        self::assertTrue($this->dispatcher->hasListeners(self::postFoo));
        self::assertCount(1, $this->dispatcher->getListeners(self::preFoo));
        self::assertCount(1, $this->dispatcher->getListeners(self::postFoo));
        self::assertCount(2, $this->dispatcher->getListeners());
    }

    public function testGetListenersSortsByPriority()
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();
        $listener1->name = '1';
        $listener2->name = '2';
        $listener3->name = '3';

        $this->dispatcher->addListener('pre.foo', [$listener1, 'preFoo'], -10);
        $this->dispatcher->addListener('pre.foo', [$listener2, 'preFoo'], 10);
        $this->dispatcher->addListener('pre.foo', [$listener3, 'preFoo']);

        $expected = [
            [$listener2, 'preFoo'],
            [$listener3, 'preFoo'],
            [$listener1, 'preFoo'],
        ];

        self::assertSame($expected, $this->dispatcher->getListeners('pre.foo'));
    }

    public function testGetAllListenersSortsByPriority()
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();
        $listener4 = new TestEventListener();
        $listener5 = new TestEventListener();
        $listener6 = new TestEventListener();

        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);
        $this->dispatcher->addListener('pre.foo', $listener3, 10);
        $this->dispatcher->addListener('post.foo', $listener4, -10);
        $this->dispatcher->addListener('post.foo', $listener5);
        $this->dispatcher->addListener('post.foo', $listener6, 10);

        $expected = [
            'pre.foo' => [$listener3, $listener2, $listener1],
            'post.foo' => [$listener6, $listener5, $listener4],
        ];

        self::assertSame($expected, $this->dispatcher->getListeners());
    }

    public function testGetListenerPriority()
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();

        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);

        self::assertSame(-10, $this->dispatcher->getListenerPriority('pre.foo', $listener1));
        self::assertSame(0, $this->dispatcher->getListenerPriority('pre.foo', $listener2));
        self::assertNull($this->dispatcher->getListenerPriority('pre.bar', $listener2));
        self::assertNull($this->dispatcher->getListenerPriority('pre.foo', function () {}));
    }

    public function testDispatch()
    {
        $this->dispatcher->addListener('pre.foo', [$this->listener, 'preFoo']);
        $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo']);
        $this->dispatcher->dispatch(new Event(), self::preFoo);
        self::assertTrue($this->listener->preFooInvoked);
        self::assertFalse($this->listener->postFooInvoked);
        self::assertInstanceOf(Event::class, $this->dispatcher->dispatch(new Event(), 'noevent'));
        self::assertInstanceOf(Event::class, $this->dispatcher->dispatch(new Event(), self::preFoo));
        $event = new Event();
        $return = $this->dispatcher->dispatch($event, self::preFoo);
        self::assertSame($event, $return);
    }

    public function testDispatchForClosure()
    {
        $invoked = 0;
        $listener = function () use (&$invoked) {
            ++$invoked;
        };
        $this->dispatcher->addListener('pre.foo', $listener);
        $this->dispatcher->addListener('post.foo', $listener);
        $this->dispatcher->dispatch(new Event(), self::preFoo);
        self::assertEquals(1, $invoked);
    }

    public function testStopEventPropagation()
    {
        $otherListener = new TestEventListener();

        // postFoo() stops the propagation, so only one listener should
        // be executed
        // Manually set priority to enforce $this->listener to be called first
        $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo'], 10);
        $this->dispatcher->addListener('post.foo', [$otherListener, 'postFoo']);
        $this->dispatcher->dispatch(new Event(), self::postFoo);
        self::assertTrue($this->listener->postFooInvoked);
        self::assertFalse($otherListener->postFooInvoked);
    }

    public function testDispatchByPriority()
    {
        $invoked = [];
        $listener1 = function () use (&$invoked) {
            $invoked[] = '1';
        };
        $listener2 = function () use (&$invoked) {
            $invoked[] = '2';
        };
        $listener3 = function () use (&$invoked) {
            $invoked[] = '3';
        };
        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);
        $this->dispatcher->addListener('pre.foo', $listener3, 10);
        $this->dispatcher->dispatch(new Event(), self::preFoo);
        self::assertEquals(['3', '2', '1'], $invoked);
    }

    public function testRemoveListener()
    {
        $this->dispatcher->addListener('pre.bar', $this->listener);
        self::assertTrue($this->dispatcher->hasListeners(self::preBar));
        $this->dispatcher->removeListener('pre.bar', $this->listener);
        self::assertFalse($this->dispatcher->hasListeners(self::preBar));
        $this->dispatcher->removeListener('notExists', $this->listener);
    }

    public function testAddSubscriber()
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);
        self::assertTrue($this->dispatcher->hasListeners(self::preFoo));
        self::assertTrue($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testAddSubscriberWithPriorities()
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $eventSubscriber = new TestEventSubscriberWithPriorities();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $listeners = $this->dispatcher->getListeners('pre.foo');
        self::assertTrue($this->dispatcher->hasListeners(self::preFoo));
        self::assertCount(2, $listeners);
        self::assertInstanceOf(TestEventSubscriberWithPriorities::class, $listeners[0][0]);
    }

    public function testAddSubscriberWithMultipleListeners()
    {
        $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $listeners = $this->dispatcher->getListeners('pre.foo');
        self::assertTrue($this->dispatcher->hasListeners(self::preFoo));
        self::assertCount(2, $listeners);
        self::assertEquals('preFoo2', $listeners[0][1]);
    }

    public function testRemoveSubscriber()
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);
        self::assertTrue($this->dispatcher->hasListeners(self::preFoo));
        self::assertTrue($this->dispatcher->hasListeners(self::postFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        self::assertFalse($this->dispatcher->hasListeners(self::preFoo));
        self::assertFalse($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testRemoveSubscriberWithPriorities()
    {
        $eventSubscriber = new TestEventSubscriberWithPriorities();
        $this->dispatcher->addSubscriber($eventSubscriber);
        self::assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        self::assertFalse($this->dispatcher->hasListeners(self::preFoo));
    }

    public function testRemoveSubscriberWithMultipleListeners()
    {
        $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
        $this->dispatcher->addSubscriber($eventSubscriber);
        self::assertTrue($this->dispatcher->hasListeners(self::preFoo));
        self::assertCount(2, $this->dispatcher->getListeners(self::preFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        self::assertFalse($this->dispatcher->hasListeners(self::preFoo));
    }

    public function testEventReceivesTheDispatcherInstanceAsArgument()
    {
        $listener = new TestWithDispatcher();
        $this->dispatcher->addListener('test', [$listener, 'foo']);
        self::assertNull($listener->name);
        self::assertNull($listener->dispatcher);
        $this->dispatcher->dispatch(new Event(), 'test');
        self::assertEquals('test', $listener->name);
        self::assertSame($this->dispatcher, $listener->dispatcher);
    }

    /**
     * @see https://bugs.php.net/62976
     *
     * This bug affects:
     *  - The PHP 5.3 branch for versions < 5.3.18
     *  - The PHP 5.4 branch for versions < 5.4.8
     *  - The PHP 5.5 branch is not affected
     */
    public function testWorkaroundForPhpBug62976()
    {
        $dispatcher = $this->createEventDispatcher();
        $dispatcher->addListener('bug.62976', new CallableClass());
        $dispatcher->removeListener('bug.62976', function () {});
        self::assertTrue($dispatcher->hasListeners('bug.62976'));
    }

    public function testHasListenersWhenAddedCallbackListenerIsRemoved()
    {
        $listener = function () {};
        $this->dispatcher->addListener('foo', $listener);
        $this->dispatcher->removeListener('foo', $listener);
        self::assertFalse($this->dispatcher->hasListeners());
    }

    public function testGetListenersWhenAddedCallbackListenerIsRemoved()
    {
        $listener = function () {};
        $this->dispatcher->addListener('foo', $listener);
        $this->dispatcher->removeListener('foo', $listener);
        self::assertSame([], $this->dispatcher->getListeners());
    }

    public function testHasListenersWithoutEventsReturnsFalseAfterHasListenersWithEventHasBeenCalled()
    {
        self::assertFalse($this->dispatcher->hasListeners('foo'));
        self::assertFalse($this->dispatcher->hasListeners());
    }

    public function testHasListenersIsLazy()
    {
        $called = 0;
        $listener = [function () use (&$called) { ++$called; }, 'onFoo'];
        $this->dispatcher->addListener('foo', $listener);
        self::assertTrue($this->dispatcher->hasListeners());
        self::assertTrue($this->dispatcher->hasListeners('foo'));
        self::assertSame(0, $called);
    }

    public function testDispatchLazyListener()
    {
        $dispatcher = new TestWithDispatcher();
        $called = 0;
        $factory = function () use (&$called, $dispatcher) {
            ++$called;

            return $dispatcher;
        };
        $this->dispatcher->addListener('foo', [$factory, 'foo']);
        self::assertSame(0, $called);
        $this->dispatcher->dispatch(new Event(), 'foo');
        self::assertFalse($dispatcher->invoked);
        $this->dispatcher->dispatch(new Event(), 'foo');
        self::assertSame(1, $called);

        $this->dispatcher->addListener('bar', [$factory]);
        self::assertSame(1, $called);
        $this->dispatcher->dispatch(new Event(), 'bar');
        self::assertTrue($dispatcher->invoked);
        $this->dispatcher->dispatch(new Event(), 'bar');
        self::assertSame(2, $called);
    }

    public function testRemoveFindsLazyListeners()
    {
        $test = new TestWithDispatcher();
        $factory = function () use ($test) { return $test; };

        $this->dispatcher->addListener('foo', [$factory, 'foo']);
        self::assertTrue($this->dispatcher->hasListeners('foo'));
        $this->dispatcher->removeListener('foo', [$test, 'foo']);
        self::assertFalse($this->dispatcher->hasListeners('foo'));

        $this->dispatcher->addListener('foo', [$test, 'foo']);
        self::assertTrue($this->dispatcher->hasListeners('foo'));
        $this->dispatcher->removeListener('foo', [$factory, 'foo']);
        self::assertFalse($this->dispatcher->hasListeners('foo'));
    }

    public function testPriorityFindsLazyListeners()
    {
        $test = new TestWithDispatcher();
        $factory = function () use ($test) { return $test; };

        $this->dispatcher->addListener('foo', [$factory, 'foo'], 3);
        self::assertSame(3, $this->dispatcher->getListenerPriority('foo', [$test, 'foo']));
        $this->dispatcher->removeListener('foo', [$factory, 'foo']);

        $this->dispatcher->addListener('foo', [$test, 'foo'], 5);
        self::assertSame(5, $this->dispatcher->getListenerPriority('foo', [$factory, 'foo']));
    }

    public function testGetLazyListeners()
    {
        $test = new TestWithDispatcher();
        $factory = function () use ($test) { return $test; };

        $this->dispatcher->addListener('foo', [$factory, 'foo'], 3);
        self::assertSame([[$test, 'foo']], $this->dispatcher->getListeners('foo'));

        $this->dispatcher->removeListener('foo', [$test, 'foo']);
        $this->dispatcher->addListener('bar', [$factory, 'foo'], 3);
        self::assertSame(['bar' => [[$test, 'foo']]], $this->dispatcher->getListeners());
    }

    public function testMutatingWhilePropagationIsStopped()
    {
        $testLoaded = false;
        $test = new TestEventListener();
        $this->dispatcher->addListener('foo', [$test, 'postFoo']);
        $this->dispatcher->addListener('foo', [function () use ($test, &$testLoaded) {
            $testLoaded = true;

            return $test;
        }, 'preFoo']);

        $this->dispatcher->dispatch(new Event(), 'foo');

        self::assertTrue($test->postFooInvoked);
        self::assertFalse($test->preFooInvoked);

        self::assertsame(0, $this->dispatcher->getListenerPriority('foo', [$test, 'preFoo']));

        $test->preFoo(new Event());
        $this->dispatcher->dispatch(new Event(), 'foo');

        self::assertTrue($testLoaded);
    }

    /**
     * @requires PHP 8.1
     */
    public function testNamedClosures()
    {
        $listener = new TestEventListener();

        $callback1 = \Closure::fromCallable($listener);
        $callback2 = \Closure::fromCallable($listener);
        $callback3 = \Closure::fromCallable(new TestEventListener());

        self::assertNotSame($callback1, $callback2);
        self::assertNotSame($callback1, $callback3);
        self::assertNotSame($callback2, $callback3);
        self::assertTrue($callback1 == $callback2);
        self::assertFalse($callback1 == $callback3);

        $this->dispatcher->addListener('foo', $callback1, 3);
        $this->dispatcher->addListener('foo', $callback2, 2);
        $this->dispatcher->addListener('foo', $callback3, 1);

        self::assertSame(3, $this->dispatcher->getListenerPriority('foo', $callback1));
        self::assertSame(3, $this->dispatcher->getListenerPriority('foo', $callback2));

        $this->dispatcher->removeListener('foo', $callback1);

        self::assertSame(['foo' => [$callback3]], $this->dispatcher->getListeners());
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}

class TestEventListener
{
    public $name;
    public $preFooInvoked = false;
    public $postFooInvoked = false;

    /* Listener methods */

    public function preFoo($e)
    {
        $this->preFooInvoked = true;
    }

    public function postFoo($e)
    {
        $this->postFooInvoked = true;

        if (!$this->preFooInvoked) {
            $e->stopPropagation();
        }
    }

    public function __invoke()
    {
    }
}

class TestWithDispatcher
{
    public $name;
    public $dispatcher;
    public $invoked = false;

    public function foo($e, $name, $dispatcher)
    {
        $this->name = $name;
        $this->dispatcher = $dispatcher;
    }

    public function __invoke($e, $name, $dispatcher)
    {
        $this->name = $name;
        $this->dispatcher = $dispatcher;
        $this->invoked = true;
    }
}

class TestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['pre.foo' => 'preFoo', 'post.foo' => 'postFoo'];
    }
}

class TestEventSubscriberWithPriorities implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'pre.foo' => ['preFoo', 10],
            'post.foo' => ['postFoo'],
        ];
    }
}

class TestEventSubscriberWithMultipleListeners implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['pre.foo' => [
            ['preFoo1'],
            ['preFoo2', 10],
        ]];
    }
}

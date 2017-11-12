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
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEventDispatcherTest extends TestCase
{
    /* Some pseudo events */
    const preFoo = 'pre.foo';
    const postFoo = 'post.foo';
    const preBar = 'pre.bar';
    const postBar = 'post.bar';

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

    abstract protected function createEventDispatcher(): void;

    public function testInitialState(): void
    {
        $this->assertEquals(array(), $this->dispatcher->getListeners());
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
        $this->assertFalse($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testAddListener(): void
    {
        $this->dispatcher->addListener('pre.foo', array($this->listener, 'preFoo'));
        $this->dispatcher->addListener('post.foo', array($this->listener, 'postFoo'));
        $this->assertTrue($this->dispatcher->hasListeners());
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
        $this->assertCount(1, $this->dispatcher->getListeners(self::preFoo));
        $this->assertCount(1, $this->dispatcher->getListeners(self::postFoo));
        $this->assertCount(2, $this->dispatcher->getListeners());
    }

    public function testGetListenersSortsByPriority(): void
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();
        $listener1->name = '1';
        $listener2->name = '2';
        $listener3->name = '3';

        $this->dispatcher->addListener('pre.foo', array($listener1, 'preFoo'), -10);
        $this->dispatcher->addListener('pre.foo', array($listener2, 'preFoo'), 10);
        $this->dispatcher->addListener('pre.foo', array($listener3, 'preFoo'));

        $expected = array(
            array($listener2, 'preFoo'),
            array($listener3, 'preFoo'),
            array($listener1, 'preFoo'),
        );

        $this->assertSame($expected, $this->dispatcher->getListeners('pre.foo'));
    }

    public function testGetAllListenersSortsByPriority(): void
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

        $expected = array(
            'pre.foo' => array($listener3, $listener2, $listener1),
            'post.foo' => array($listener6, $listener5, $listener4),
        );

        $this->assertSame($expected, $this->dispatcher->getListeners());
    }

    public function testGetListenerPriority(): void
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();

        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);

        $this->assertSame(-10, $this->dispatcher->getListenerPriority('pre.foo', $listener1));
        $this->assertSame(0, $this->dispatcher->getListenerPriority('pre.foo', $listener2));
        $this->assertNull($this->dispatcher->getListenerPriority('pre.bar', $listener2));
        $this->assertNull($this->dispatcher->getListenerPriority('pre.foo', function (): void {}));
    }

    public function testDispatch(): void
    {
        $this->dispatcher->addListener('pre.foo', array($this->listener, 'preFoo'));
        $this->dispatcher->addListener('post.foo', array($this->listener, 'postFoo'));
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertTrue($this->listener->preFooInvoked);
        $this->assertFalse($this->listener->postFooInvoked);
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\Event', $this->dispatcher->dispatch('noevent'));
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\Event', $this->dispatcher->dispatch(self::preFoo));
        $event = new Event();
        $return = $this->dispatcher->dispatch(self::preFoo, $event);
        $this->assertSame($event, $return);
    }

    public function testDispatchForClosure(): void
    {
        $invoked = 0;
        $listener = function () use (&$invoked): void {
            ++$invoked;
        };
        $this->dispatcher->addListener('pre.foo', $listener);
        $this->dispatcher->addListener('post.foo', $listener);
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertEquals(1, $invoked);
    }

    public function testStopEventPropagation(): void
    {
        $otherListener = new TestEventListener();

        // postFoo() stops the propagation, so only one listener should
        // be executed
        // Manually set priority to enforce $this->listener to be called first
        $this->dispatcher->addListener('post.foo', array($this->listener, 'postFoo'), 10);
        $this->dispatcher->addListener('post.foo', array($otherListener, 'preFoo'));
        $this->dispatcher->dispatch(self::postFoo);
        $this->assertTrue($this->listener->postFooInvoked);
        $this->assertFalse($otherListener->postFooInvoked);
    }

    public function testDispatchByPriority(): void
    {
        $invoked = array();
        $listener1 = function () use (&$invoked): void {
            $invoked[] = '1';
        };
        $listener2 = function () use (&$invoked): void {
            $invoked[] = '2';
        };
        $listener3 = function () use (&$invoked): void {
            $invoked[] = '3';
        };
        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);
        $this->dispatcher->addListener('pre.foo', $listener3, 10);
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertEquals(array('3', '2', '1'), $invoked);
    }

    public function testRemoveListener(): void
    {
        $this->dispatcher->addListener('pre.bar', $this->listener);
        $this->assertTrue($this->dispatcher->hasListeners(self::preBar));
        $this->dispatcher->removeListener('pre.bar', $this->listener);
        $this->assertFalse($this->dispatcher->hasListeners(self::preBar));
        $this->dispatcher->removeListener('notExists', $this->listener);
    }

    public function testAddSubscriber(): void
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testAddSubscriberWithPriorities(): void
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $eventSubscriber = new TestEventSubscriberWithPriorities();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $listeners = $this->dispatcher->getListeners('pre.foo');
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertCount(2, $listeners);
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\Tests\TestEventSubscriberWithPriorities', $listeners[0][0]);
    }

    public function testAddSubscriberWithMultipleListeners(): void
    {
        $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $listeners = $this->dispatcher->getListeners('pre.foo');
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertCount(2, $listeners);
        $this->assertEquals('preFoo2', $listeners[0][1]);
    }

    public function testRemoveSubscriber(): void
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
        $this->assertFalse($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testRemoveSubscriberWithPriorities(): void
    {
        $eventSubscriber = new TestEventSubscriberWithPriorities();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
    }

    public function testRemoveSubscriberWithMultipleListeners(): void
    {
        $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertCount(2, $this->dispatcher->getListeners(self::preFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
    }

    public function testEventReceivesTheDispatcherInstanceAsArgument(): void
    {
        $listener = new TestWithDispatcher();
        $this->dispatcher->addListener('test', array($listener, 'foo'));
        $this->assertNull($listener->name);
        $this->assertNull($listener->dispatcher);
        $this->dispatcher->dispatch('test');
        $this->assertEquals('test', $listener->name);
        $this->assertSame($this->dispatcher, $listener->dispatcher);
    }

    /**
     * @see https://bugs.php.net/bug.php?id=62976
     *
     * This bug affects:
     *  - The PHP 5.3 branch for versions < 5.3.18
     *  - The PHP 5.4 branch for versions < 5.4.8
     *  - The PHP 5.5 branch is not affected
     */
    public function testWorkaroundForPhpBug62976(): void
    {
        $dispatcher = $this->createEventDispatcher();
        $dispatcher->addListener('bug.62976', new CallableClass());
        $dispatcher->removeListener('bug.62976', function (): void {});
        $this->assertTrue($dispatcher->hasListeners('bug.62976'));
    }

    public function testHasListenersWhenAddedCallbackListenerIsRemoved(): void
    {
        $listener = function (): void {};
        $this->dispatcher->addListener('foo', $listener);
        $this->dispatcher->removeListener('foo', $listener);
        $this->assertFalse($this->dispatcher->hasListeners());
    }

    public function testGetListenersWhenAddedCallbackListenerIsRemoved(): void
    {
        $listener = function (): void {};
        $this->dispatcher->addListener('foo', $listener);
        $this->dispatcher->removeListener('foo', $listener);
        $this->assertSame(array(), $this->dispatcher->getListeners());
    }

    public function testHasListenersWithoutEventsReturnsFalseAfterHasListenersWithEventHasBeenCalled(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('foo'));
        $this->assertFalse($this->dispatcher->hasListeners());
    }

    public function testHasListenersIsLazy(): void
    {
        $called = 0;
        $listener = array(function () use (&$called): void { ++$called; }, 'onFoo');
        $this->dispatcher->addListener('foo', $listener);
        $this->assertTrue($this->dispatcher->hasListeners());
        $this->assertTrue($this->dispatcher->hasListeners('foo'));
        $this->assertSame(0, $called);
    }

    public function testDispatchLazyListener(): void
    {
        $called = 0;
        $factory = function () use (&$called) {
            ++$called;

            return new TestWithDispatcher();
        };
        $this->dispatcher->addListener('foo', array($factory, 'foo'));
        $this->assertSame(0, $called);
        $this->dispatcher->dispatch('foo', new Event());
        $this->dispatcher->dispatch('foo', new Event());
        $this->assertSame(1, $called);
    }

    public function testRemoveFindsLazyListeners(): void
    {
        $test = new TestWithDispatcher();
        $factory = function () use ($test) { return $test; };

        $this->dispatcher->addListener('foo', array($factory, 'foo'));
        $this->assertTrue($this->dispatcher->hasListeners('foo'));
        $this->dispatcher->removeListener('foo', array($test, 'foo'));
        $this->assertFalse($this->dispatcher->hasListeners('foo'));

        $this->dispatcher->addListener('foo', array($test, 'foo'));
        $this->assertTrue($this->dispatcher->hasListeners('foo'));
        $this->dispatcher->removeListener('foo', array($factory, 'foo'));
        $this->assertFalse($this->dispatcher->hasListeners('foo'));
    }

    public function testPriorityFindsLazyListeners(): void
    {
        $test = new TestWithDispatcher();
        $factory = function () use ($test) { return $test; };

        $this->dispatcher->addListener('foo', array($factory, 'foo'), 3);
        $this->assertSame(3, $this->dispatcher->getListenerPriority('foo', array($test, 'foo')));
        $this->dispatcher->removeListener('foo', array($factory, 'foo'));

        $this->dispatcher->addListener('foo', array($test, 'foo'), 5);
        $this->assertSame(5, $this->dispatcher->getListenerPriority('foo', array($factory, 'foo')));
    }

    public function testGetLazyListeners(): void
    {
        $test = new TestWithDispatcher();
        $factory = function () use ($test) { return $test; };

        $this->dispatcher->addListener('foo', array($factory, 'foo'), 3);
        $this->assertSame(array(array($test, 'foo')), $this->dispatcher->getListeners('foo'));

        $this->dispatcher->removeListener('foo', array($test, 'foo'));
        $this->dispatcher->addListener('bar', array($factory, 'foo'), 3);
        $this->assertSame(array('bar' => array(array($test, 'foo'))), $this->dispatcher->getListeners());
    }
}

class CallableClass
{
    public function __invoke(): void
    {
    }
}

class TestEventListener
{
    public $preFooInvoked = false;
    public $postFooInvoked = false;

    /* Listener methods */

    public function preFoo(Event $e): void
    {
        $this->preFooInvoked = true;
    }

    public function postFoo(Event $e): void
    {
        $this->postFooInvoked = true;

        $e->stopPropagation();
    }
}

class TestWithDispatcher
{
    public $name;
    public $dispatcher;

    public function foo(Event $e, $name, $dispatcher): void
    {
        $this->name = $name;
        $this->dispatcher = $dispatcher;
    }
}

class TestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array('pre.foo' => 'preFoo', 'post.foo' => 'postFoo');
    }
}

class TestEventSubscriberWithPriorities implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'pre.foo' => array('preFoo', 10),
            'post.foo' => array('postFoo'),
            );
    }
}

class TestEventSubscriberWithMultipleListeners implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array('pre.foo' => array(
            array('preFoo1'),
            array('preFoo2', 10),
        ));
    }
}

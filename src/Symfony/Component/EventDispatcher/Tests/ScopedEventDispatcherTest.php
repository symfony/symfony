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

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\ScopedEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

class ScopedEventDispatcherTest extends EventDispatcherTest
{
    private EventDispatcher $parent;

    protected function createEventDispatcher()
    {
        return new ScopedEventDispatcher($this->parent = new EventDispatcher());
    }

    public function testTheParentIsNotMutated()
    {
        $dispatcher = $this->createEventDispatcher();
        $dispatcher->addListener('pre.foo', $listener = static function () {});
        $dispatcher->dispatch(new Event(), 'pre.foo');

        $this->assertSame([], $this->parent->getListeners());
        $this->assertFalse($this->parent->hasListeners('pre.foo'));
        $this->assertNull($this->parent->getListenerPriority('pre.foo', $listener));
    }

    public function testTheListenersOfTheParentAreCalled()
    {
        $dispatcher = $this->createEventDispatcher();
        $called = [];
        $this->parent->addListener('pre.foo', static function () use (&$called) { $called[] = 'parent'; });
        $dispatcher->addListener('pre.foo', static function () use (&$called) { $called[] = 'scoped'; });

        $dispatcher->dispatch(new Event(), 'pre.foo');
        $dispatcher->dispatch(new Event(), 'pre.foo');

        $this->assertSame(['parent', 'scoped', 'parent', 'scoped'], $called);
    }

    public function testTheListenersOfTheParentKeepTheirPriority()
    {
        $dispatcher = $this->createEventDispatcher();
        $called = [];
        $this->parent->addListener('pre.foo', static function () use (&$called) { $called[] = 'high'; }, 10);
        $this->parent->addListener('pre.foo', static function () use (&$called) { $called[] = 'low'; }, -10);
        $dispatcher->addListener('pre.foo', static function () use (&$called) { $called[] = 'scoped'; });

        $dispatcher->dispatch(new Event(), 'pre.foo');

        $this->assertSame(['high', 'scoped', 'low'], $called);
    }

    public function testAnEventNobodyWasAddedForIsDispatchedByTheParent()
    {
        $dispatcher = $this->createEventDispatcher();
        $dispatched = null;
        $this->parent->addListener('pre.foo', static function ($event, $eventName, $dispatcher) use (&$dispatched) { $dispatched = $dispatcher; });

        $dispatcher->dispatch($event = new Event(), 'pre.foo');

        $this->assertSame($this->parent, $dispatched);
        $this->assertSame([], $dispatcher->getListeners('post.foo'));
    }

    public function testGetListeners()
    {
        $dispatcher = $this->createEventDispatcher();
        $this->parent->addListener('pre.foo', $fromParent = static function () {}, 10);
        $dispatcher->addListener('pre.foo', $scoped = static function () {});
        $dispatcher->addListener('post.foo', $other = static function () {});

        $this->assertSame([$fromParent, $scoped], $dispatcher->getListeners('pre.foo'));
        $this->assertSame(['pre.foo' => [$fromParent, $scoped], 'post.foo' => [$other]], $dispatcher->getListeners());
        $this->assertSame(10, $dispatcher->getListenerPriority('pre.foo', $fromParent));
        $this->assertSame(0, $dispatcher->getListenerPriority('pre.foo', $scoped));
        $this->assertTrue($dispatcher->hasListeners('pre.foo'));
        $this->assertTrue($dispatcher->hasListeners());
    }

    public function testStopPropagationAppliesToTheListenersOfTheParent()
    {
        $dispatcher = $this->createEventDispatcher();
        $called = false;
        $this->parent->addListener('pre.foo', static function () use (&$called) { $called = true; }, -10);
        $dispatcher->addListener('pre.foo', static function (Event $event) { $event->stopPropagation(); });

        $dispatcher->dispatch(new Event(), 'pre.foo');

        $this->assertFalse($called);
    }
}

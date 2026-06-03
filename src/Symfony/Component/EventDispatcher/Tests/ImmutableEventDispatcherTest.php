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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\ImmutableEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ImmutableEventDispatcherTest extends TestCase
{
    public function testDispatchDelegates()
    {
        $innerDispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher = new ImmutableEventDispatcher($innerDispatcher);

        $event = new Event();
        $resultEvent = new Event();

        $innerDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event, 'event')
            ->willReturn($resultEvent);

        $this->assertSame($resultEvent, $dispatcher->dispatch($event, 'event'));
    }

    public function testGetListenersDelegates()
    {
        $innerDispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher = new ImmutableEventDispatcher($innerDispatcher);

        $innerDispatcher->expects($this->once())
            ->method('getListeners')
            ->with('event')
            ->willReturn(['result']);

        $this->assertSame(['result'], $dispatcher->getListeners('event'));
    }

    public function testHasListenersDelegates()
    {
        $innerDispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher = new ImmutableEventDispatcher($innerDispatcher);

        $innerDispatcher->expects($this->once())
            ->method('hasListeners')
            ->with('event')
            ->willReturn(true);

        $this->assertTrue($dispatcher->hasListeners('event'));
    }

    public function testAddListenerDisallowed()
    {
        $dispatcher = new ImmutableEventDispatcher(new EventDispatcher());

        $this->expectException(\BadMethodCallException::class);
        $dispatcher->addListener('event', static fn () => 'foo');
    }

    public function testAddSubscriberDisallowed()
    {
        $dispatcher = new ImmutableEventDispatcher(new EventDispatcher());

        $this->expectException(\BadMethodCallException::class);
        $subscriber = $this->createStub(EventSubscriberInterface::class);

        $dispatcher->addSubscriber($subscriber);
    }

    public function testRemoveListenerDisallowed()
    {
        $dispatcher = new ImmutableEventDispatcher(new EventDispatcher());

        $this->expectException(\BadMethodCallException::class);
        $dispatcher->removeListener('event', static fn () => 'foo');
    }

    public function testRemoveSubscriberDisallowed()
    {
        $dispatcher = new ImmutableEventDispatcher(new EventDispatcher());

        $this->expectException(\BadMethodCallException::class);
        $subscriber = $this->createStub(EventSubscriberInterface::class);

        $dispatcher->removeSubscriber($subscriber);
    }
}

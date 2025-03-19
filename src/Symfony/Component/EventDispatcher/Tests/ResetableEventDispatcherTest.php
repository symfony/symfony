<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\ResetableEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Alexander Dmitryuk <sasha_dmitruk@mail.ru>
 */
class ResetableEventDispatcherTest extends TestCase
{
    private EventDispatcherInterface&MockObject $innerDispatcher;
    private ResetableEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->innerDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->dispatcher = new ResetableEventDispatcher($this->innerDispatcher);
    }

    public function testDispatchDelegates(): void
    {
        $event = new Event();

        $this->innerDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event, 'event')
            ->willReturn($event);

        $this->assertSame($event, $this->dispatcher->dispatch($event, 'event'));
    }

    public function testGetListenersDelegates(): void
    {
        $this->innerDispatcher->expects($this->once())
            ->method('getListeners')
            ->with('event')
            ->willReturn(['result']);

        $this->assertSame(['result'], $this->dispatcher->getListeners('event'));
    }

    public function testHasListenersDelegates(): void
    {
        $this->innerDispatcher->expects($this->once())
            ->method('hasListeners')
            ->with('event')
            ->willReturn(true);

        $this->assertTrue($this->dispatcher->hasListeners('event'));
    }

    public function testAddListenerDelegatesAndMissingAfterReset(): void
    {
        $fn = fn () => 'foo';
        $this->innerDispatcher->expects($this->once())
            ->method('addListener')
            ->with('event', $fn);
        $this->innerDispatcher->expects($this->once())
            ->method('removeListener')
            ->with('event', $fn);
        $this->dispatcher->addListener('event', $fn);
        $this->dispatcher->reset();
    }

    public function testAddSubscriberDelegatesAndMissingAfterReset(): void
    {
        $subscriber = $this->createMock(EventSubscriberInterface::class);
        $this->innerDispatcher->expects($this->once())
            ->method('addSubscriber')
            ->with($subscriber);
        $this->innerDispatcher->expects($this->once())
            ->method('removeSubscriber')
            ->with($subscriber);
        $this->dispatcher->addSubscriber($subscriber);
        $this->dispatcher->reset();
    }

    public function testRemoveListenerDelegates(): void
    {
        $fn = fn () => 'foo';
        $this->innerDispatcher->expects($this->once())
            ->method('removeListener')
            ->with('event', $fn);
        $this->dispatcher->removeListener('event', $fn);
    }

    public function testRemoveSubscriberDelegates(): void
    {
        $subscriber = $this->createMock(EventSubscriberInterface::class);
        $this->innerDispatcher->expects($this->once())
            ->method('removeSubscriber')
            ->with($subscriber);
        $this->dispatcher->removeSubscriber($subscriber);
    }

    public function testGetListenersPriorityDelegates(): void
    {
        $fn = fn () => 'foo';
        $this->innerDispatcher->expects($this->once())
            ->method('getListenerPriority')
            ->with('event', $fn)
            ->willReturn(1);

        $this->assertSame(1, $this->dispatcher->getListenerPriority('event', $fn));
    }
}

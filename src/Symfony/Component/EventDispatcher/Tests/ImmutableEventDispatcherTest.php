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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\ImmutableEventDispatcher;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ImmutableEventDispatcherTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $innerDispatcher;

    /**
     * @var ImmutableEventDispatcher
     */
    private $dispatcher;

    protected function setUp(): void
    {
        $this->innerDispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->dispatcher = new ImmutableEventDispatcher($this->innerDispatcher);
    }

    public function testDispatchDelegates()
    {
        $event = new Event();
        $resultEvent = new Event();

        $this->innerDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event, 'event')
            ->willReturn($resultEvent);

        $this->assertSame($resultEvent, $this->dispatcher->dispatch('event', $event));
    }

    public function testGetListenersDelegates()
    {
        $this->innerDispatcher->expects($this->once())
            ->method('getListeners')
            ->with('event')
            ->willReturn(['result']);

        $this->assertSame(['result'], $this->dispatcher->getListeners('event'));
    }

    public function testHasListenersDelegates()
    {
        $this->innerDispatcher->expects($this->once())
            ->method('hasListeners')
            ->with('event')
            ->willReturn(true);

        $this->assertTrue($this->dispatcher->hasListeners('event'));
    }

    public function testAddListenerDisallowed()
    {
        $this->expectException('\BadMethodCallException');
        $this->dispatcher->addListener('event', function () { return 'foo'; });
    }

    public function testAddSubscriberDisallowed()
    {
        $this->expectException('\BadMethodCallException');
        $subscriber = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventSubscriberInterface')->getMock();

        $this->dispatcher->addSubscriber($subscriber);
    }

    public function testRemoveListenerDisallowed()
    {
        $this->expectException('\BadMethodCallException');
        $this->dispatcher->removeListener('event', function () { return 'foo'; });
    }

    public function testRemoveSubscriberDisallowed()
    {
        $this->expectException('\BadMethodCallException');
        $subscriber = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventSubscriberInterface')->getMock();

        $this->dispatcher->removeSubscriber($subscriber);
    }
}

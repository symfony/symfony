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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\ImmutableEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ImmutableEventDispatcherTest extends TestCase
{
    /**
     * @var MockObject&EventDispatcherInterface
     */
    private $innerDispatcher;

    /**
     * @var ImmutableEventDispatcher
     */
    private $dispatcher;

    protected function setUp(): void
    {
        $this->innerDispatcher = self::createMock(EventDispatcherInterface::class);
        $this->dispatcher = new ImmutableEventDispatcher($this->innerDispatcher);
    }

    public function testDispatchDelegates()
    {
        $event = new Event();
        $resultEvent = new Event();

        $this->innerDispatcher->expects(self::once())
            ->method('dispatch')
            ->with($event, 'event')
            ->willReturn($resultEvent);

        self::assertSame($resultEvent, $this->dispatcher->dispatch($event, 'event'));
    }

    public function testGetListenersDelegates()
    {
        $this->innerDispatcher->expects(self::once())
            ->method('getListeners')
            ->with('event')
            ->willReturn(['result']);

        self::assertSame(['result'], $this->dispatcher->getListeners('event'));
    }

    public function testHasListenersDelegates()
    {
        $this->innerDispatcher->expects(self::once())
            ->method('hasListeners')
            ->with('event')
            ->willReturn(true);

        self::assertTrue($this->dispatcher->hasListeners('event'));
    }

    public function testAddListenerDisallowed()
    {
        self::expectException(\BadMethodCallException::class);
        $this->dispatcher->addListener('event', function () { return 'foo'; });
    }

    public function testAddSubscriberDisallowed()
    {
        self::expectException(\BadMethodCallException::class);
        $subscriber = self::createMock(EventSubscriberInterface::class);

        $this->dispatcher->addSubscriber($subscriber);
    }

    public function testRemoveListenerDisallowed()
    {
        self::expectException(\BadMethodCallException::class);
        $this->dispatcher->removeListener('event', function () { return 'foo'; });
    }

    public function testRemoveSubscriberDisallowed()
    {
        self::expectException(\BadMethodCallException::class);
        $subscriber = self::createMock(EventSubscriberInterface::class);

        $this->dispatcher->removeSubscriber($subscriber);
    }
}

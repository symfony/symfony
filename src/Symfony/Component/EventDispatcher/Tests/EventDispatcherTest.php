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

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventListenerCallerInterface;

class EventDispatcherTest extends AbstractEventDispatcherTest
{
    protected function createEventDispatcher()
    {
        return new EventDispatcher();
    }

    public function testCustomEventListenerCaller()
    {
        $listenerCallerMock = $this->getMockForAbstractClass(EventListenerCallerInterface::class);
        $listenerCallerMock
            ->expects($this->once())
            ->method('call')
            ->will($this->returnCallback(function ($listener, Event $event) {
                $listener($event);
            }));

        $dispatcher = new EventDispatcher();
        $dispatcher->setListenerCaller($listenerCallerMock);
        $dispatcher->addListener('foo', function (Event $event) {
            $event->stopPropagation();
        });

        $event = $dispatcher->dispatch('foo', new Event());

        $this->assertTrue($event->isPropagationStopped());
    }
}

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

use Symfony\Component\EventDispatcher\LazyEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LazyEventDispatcherTest extends AbstractEventDispatcherTest
{
    protected function createEventDispatcher($id = null, $listener = null)
    {
        $resolver = $this->getMock('stdClass', array('__invoke'));

        if ($id && $listener) {
            $resolver
                ->method('__invoke')
                ->with($id)
                ->willReturn($listener)
            ;
        }

        return new LazyEventDispatcher($resolver);
    }

    public function testAddAListenerService()
    {
        $event = new Event();

        $listener = $this->getMock('Symfony\Component\EventDispatcher\Tests\Listener');

        $listener
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $dispatcher = $this->createEventDispatcher('listener', $listener);
        $dispatcher->addListenerService('onEvent', array('listener', 'onEvent'));

        $dispatcher->dispatch('onEvent', $event);
    }

    public function testAddASubscriberService()
    {
        $event = new Event();

        $subscriber = $this->getMock('Symfony\Component\EventDispatcher\Tests\Subscriber');

        $subscriber
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $subscriber
            ->expects($this->once())
            ->method('onEventWithPriority')
            ->with($event)
        ;

        $subscriber
            ->expects($this->once())
            ->method('onEventNested')
            ->with($event)
        ;

        $dispatcher = $this->createEventDispatcher('subscriber', $subscriber);
        $dispatcher->addSubscriberService('subscriber', 'Symfony\Component\EventDispatcher\Tests\Subscriber');

        $dispatcher->dispatch('onEvent', $event);
        $dispatcher->dispatch('onEventWithPriority', $event);
        $dispatcher->dispatch('onEventNested', $event);
    }

    public function testPreventDuplicateListenerService()
    {
        $event = new Event();

        $listener = $this->getMock('Symfony\Component\EventDispatcher\Tests\Listener');

        $listener
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $dispatcher = $this->createEventDispatcher('listener', $listener);
        $dispatcher->addListenerService('onEvent', array('listener', 'onEvent'), 5);
        $dispatcher->addListenerService('onEvent', array('listener', 'onEvent'), 10);

        $dispatcher->dispatch('onEvent', $event);
    }

    public function testHasListenersOnLazyLoad()
    {
        $event = new Event();

        $listener = $this->getMock('Symfony\Component\EventDispatcher\Tests\Listener');

        $dispatcher = $this->createEventDispatcher('listener', $listener);
        $dispatcher->addListenerService('onEvent', array('listener', 'onEvent'));

        $listener
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $this->assertTrue($dispatcher->hasListeners());

        if ($dispatcher->hasListeners('onEvent')) {
            $dispatcher->dispatch('onEvent');
        }
    }

    public function testGetListenersOnLazyLoad()
    {
        $listener = $this->getMock('Symfony\Component\EventDispatcher\Tests\Listener');

        $dispatcher = $this->createEventDispatcher('listener', $listener);
        $dispatcher->addListenerService('onEvent', array('listener', 'onEvent'));

        $listeners = $dispatcher->getListeners();

        $this->assertTrue(isset($listeners['onEvent']));

        $this->assertCount(1, $dispatcher->getListeners('onEvent'));
    }

    public function testRemoveAfterDispatch()
    {
        $listener = $this->getMock('Symfony\Component\EventDispatcher\Tests\Listener');

        $dispatcher = $this->createEventDispatcher('listener', $listener);
        $dispatcher->addListenerService('onEvent', array('listener', 'onEvent'));

        $dispatcher->dispatch('onEvent', new Event());
        $dispatcher->removeListener('onEvent', array($listener, 'onEvent'));
        $this->assertFalse($dispatcher->hasListeners('onEvent'));
    }

    public function testRemoveBeforeDispatch()
    {
        $listener = $this->getMock('Symfony\Component\EventDispatcher\Tests\Listener');

        $dispatcher = $this->createEventDispatcher('listener', $listener);
        $dispatcher->addListenerService('onEvent', array('listener', 'onEvent'));

        $dispatcher->removeListener('onEvent', array($listener, 'onEvent'));
        $this->assertFalse($dispatcher->hasListeners('onEvent'));
    }
}

class Listener
{
    public function onEvent(Event $e)
    {
    }
}

class Subscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'onEvent' => 'onEvent',
            'onEventWithPriority' => array('onEventWithPriority', 10),
            'onEventNested' => array(array('onEventNested')),
        );
    }

    public function onEvent(Event $e)
    {
    }

    public function onEventWithPriority(Event $e)
    {
    }

    public function onEventNested(Event $e)
    {
    }
}

<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\EventDispatcher;

require_once __DIR__.'/../../bootstrap.php';

use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\EventDispatcher\EventDispatcher;

class EventDispatcherTest extends \PHPUnit_Framework_TestCase
{
  public function testConnectAndDisconnect()
  {
    $dispatcher = new EventDispatcher();

    $dispatcher->connect('bar', 'listenToBar');
    $this->assertEquals($dispatcher->getListeners('bar'), array('listenToBar'), '->connect() connects a listener to an event name');
    $dispatcher->connect('bar', 'listenToBarBar');
    $this->assertEquals($dispatcher->getListeners('bar'), array('listenToBar', 'listenToBarBar'), '->connect() can connect several listeners for the same event name');

    $dispatcher->connect('barbar', 'listenToBarBar');
    $dispatcher->disconnect('bar', 'listenToBarBar');
    $this->assertEquals($dispatcher->getListeners('bar'), array('listenToBar'), '->disconnect() disconnects a listener for an event name');
    $this->assertEquals($dispatcher->getListeners('barbar'), array('listenToBarBar'), '->disconnect() disconnects a listener for an event name');

    $this->assertTrue($dispatcher->disconnect('foobar', 'listen') === false, '->disconnect() returns false if the listener does not exist');
  }

  public function testGetHasListeners()
  {
    $dispatcher = new EventDispatcher();

    $this->assertEquals($dispatcher->hasListeners('foo'), false, '->hasListeners() returns false if the event has no listener');
    $dispatcher->connect('foo', 'listenToFoo');
    $this->assertEquals($dispatcher->hasListeners('foo'), true, '->hasListeners() returns true if the event has some listeners');
    $dispatcher->disconnect('foo', 'listenToFoo');
    $this->assertEquals($dispatcher->hasListeners('foo'), false, '->hasListeners() returns false if the event has no listener');

    $dispatcher->connect('bar', 'listenToBar');
    $this->assertEquals($dispatcher->getListeners('bar'), array('listenToBar'), '->getListeners() returns an array of listeners connected to the given event name');
    $this->assertEquals($dispatcher->getListeners('foobar'), array(), '->getListeners() returns an empty array if no listener are connected to the given event name');
  }

  public function testNotify()
  {
    $listener = new Listener();
    $dispatcher = new EventDispatcher();
    $dispatcher->connect('foo', array($listener, 'listenToFoo'));
    $dispatcher->connect('foo', array($listener, 'listenToFooBis'));
    $e = $dispatcher->notify($event = new Event(new \stdClass(), 'foo'));
    $this->assertEquals($listener->getValue(), 'listenToFoolistenToFooBis', '->notify() notifies all registered listeners in order');
    $this->assertEquals($e, $event, '->notify() returns the event object');

    $listener->reset();
    $dispatcher = new EventDispatcher();
    $dispatcher->connect('foo', array($listener, 'listenToFooBis'));
    $dispatcher->connect('foo', array($listener, 'listenToFoo'));
    $dispatcher->notify(new Event(new \stdClass(), 'foo'));
    $this->assertEquals($listener->getValue(), 'listenToFooBislistenToFoo', '->notify() notifies all registered listeners in order');
  }

  public function testNotifyUntil()
  {
    $listener = new Listener();
    $dispatcher = new EventDispatcher();
    $dispatcher->connect('foo', array($listener, 'listenToFoo'));
    $dispatcher->connect('foo', array($listener, 'listenToFooBis'));
    $e = $dispatcher->notifyUntil($event = new Event(new \stdClass(), 'foo'));
    $this->assertEquals($listener->getValue(), 'listenToFoolistenToFooBis', '->notifyUntil() notifies all registered listeners in order and stops if it returns true');
    $this->assertEquals($e, $event, '->notifyUntil() returns the event object');

    $listener->reset();
    $dispatcher = new EventDispatcher();
    $dispatcher->connect('foo', array($listener, 'listenToFooBis'));
    $dispatcher->connect('foo', array($listener, 'listenToFoo'));
    $e = $dispatcher->notifyUntil($event = new Event(new \stdClass(), 'foo'));
    $this->assertEquals($listener->getValue(), 'listenToFooBis', '->notifyUntil() notifies all registered listeners in order and stops if it returns true');
  }

  public function testFilter()
  {
    $listener = new Listener();
    $dispatcher = new EventDispatcher();
    $dispatcher->connect('foo', array($listener, 'filterFoo'));
    $dispatcher->connect('foo', array($listener, 'filterFooBis'));
    $e = $dispatcher->filter($event = new Event(new \stdClass(), 'foo'), 'foo');
    $this->assertEquals($e->getReturnValue(), '-*foo*-', '->filter() filters a value');
    $this->assertEquals($e, $event, '->filter() returns the event object');

    $listener->reset();
    $dispatcher = new EventDispatcher();
    $dispatcher->connect('foo', array($listener, 'filterFooBis'));
    $dispatcher->connect('foo', array($listener, 'filterFoo'));
    $e = $dispatcher->filter($event = new Event(new \stdClass(), 'foo'), 'foo');
    $this->assertEquals($e->getReturnValue(), '*-foo-*', '->filter() filters a value');
  }
}

class Listener
{
  protected
    $value = '';

  function filterFoo(Event $event, $foo)
  {
    return "*$foo*";
  }

  function filterFooBis(Event $event, $foo)
  {
    return "-$foo-";
  }

  function listenToFoo(Event $event)
  {
    $this->value .= 'listenToFoo';
  }

  function listenToFooBis(Event $event)
  {
    $this->value .= 'listenToFooBis';

    return true;
  }

  function getValue()
  {
    return $this->value;
  }

  function reset()
  {
    $this->value = '';
  }
}

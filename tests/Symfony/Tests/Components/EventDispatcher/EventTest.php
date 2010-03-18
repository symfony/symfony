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

class EventTest extends \PHPUnit_Framework_TestCase
{
  protected $subject;
  protected $parameters;

  public function testGetSubject()
  {
    $this->assertEquals($this->createEvent()->getSubject(), $this->subject, '->getSubject() returns the event subject');
  }

  public function testGetName()
  {
    $this->assertEquals($this->createEvent()->getName(), 'name', '->getName() returns the event name');
  }

  public function testParameters()
  {
    $event = $this->createEvent();

    $this->assertEquals($event->getParameters(), $this->parameters, '->getParameters() returns the event parameters');
    $this->assertEquals($event->getParameter('foo'), 'bar', '->getParameter() returns the value of a parameter');
    $event->setParameter('foo', 'foo');
    $this->assertEquals($event->getParameter('foo'), 'foo', '->setParameter() changes the value of a parameter');
    $this->assertTrue($event->hasParameter('foo'), '->hasParameter() returns true if the parameter is defined');
    unset($event['foo']);
    $this->assertTrue(!$event->hasParameter('foo'), '->hasParameter() returns false if the parameter is not defined');

    try
    {
      $event->getParameter('foobar');
      $this->fail('->getParameter() throws an \InvalidArgumentException exception when the parameter does not exist');
    }
    catch (\InvalidArgumentException $e)
    {
    }
    $event = new Event($this->subject, 'name', $this->parameters);
  }

  public function testSetGetReturnValue()
  {
    $event = $this->createEvent();
    $event->setReturnValue('foo');
    $this->assertEquals($event->getReturnValue(), 'foo', '->getReturnValue() returns the return value of the event');
  }

  public function testSetIsProcessed()
  {
    $event = $this->createEvent();
    $event->setProcessed(true);
    $this->assertEquals($event->isProcessed(), true, '->isProcessed() returns true if the event has been processed');
    $event->setProcessed(false);
    $this->assertEquals($event->isProcessed(), false, '->setProcessed() changes the processed status');
  }

  public function testArrayAccessInterface()
  {
    $event = $this->createEvent();

    $this->assertEquals($event['foo'], 'bar', 'Event implements the ArrayAccess interface');
    $event['foo'] = 'foo';
    $this->assertEquals($event['foo'], 'foo', 'Event implements the ArrayAccess interface');

    try
    {
      $event['foobar'];
      $this->fail('::offsetGet() throws an \InvalidArgumentException exception when the parameter does not exist');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    $this->assertTrue(isset($event['foo']), 'Event implements the ArrayAccess interface');
    unset($event['foo']);
    $this->assertTrue(!isset($event['foo']), 'Event implements the ArrayAccess interface');
  }

  protected function createEvent()
  {
    $this->subject = new \stdClass();
    $this->parameters = array('foo' => 'bar');

    return new Event($this->subject, 'name', $this->parameters);
  }
}

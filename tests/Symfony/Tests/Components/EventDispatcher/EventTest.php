<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\EventDispatcher;

use Symfony\Components\EventDispatcher\Event;

class EventTest extends \PHPUnit_Framework_TestCase
{
    protected $subject;
    protected $parameters;

    public function testGetSubject()
    {
        $event = $this->createEvent();
        $this->assertEquals($this->subject, $event->getSubject(), '->getSubject() returns the event subject');
    }

    public function testGetName()
    {
        $this->assertEquals('name', $this->createEvent()->getName(), '->getName() returns the event name');
    }

    public function testParameters()
    {
        $event = $this->createEvent();

        $this->assertEquals($this->parameters, $event->getParameters(), '->getParameters() returns the event parameters');
        $this->assertEquals('bar', $event->getParameter('foo'), '->getParameter() returns the value of a parameter');
        $event->setParameter('foo', 'foo');
        $this->assertEquals('foo', $event->getParameter('foo'), '->setParameter() changes the value of a parameter');
        $this->assertTrue($event->hasParameter('foo'), '->hasParameter() returns true if the parameter is defined');
        unset($event['foo']);
        $this->assertFalse($event->hasParameter('foo'), '->hasParameter() returns false if the parameter is not defined');

        try {
            $event->getParameter('foobar');
            $this->fail('->getParameter() throws an \InvalidArgumentException exception when the parameter does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getParameter() throws an \InvalidArgumentException exception when the parameter does not exist');
            $this->assertEquals('The event "name" has no "foobar" parameter.', $e->getMessage(), '->getParameter() throws an \InvalidArgumentException exception when the parameter does not exist');
        }
        $event = new Event($this->subject, 'name', $this->parameters);
    }

    public function testSetGetReturnValue()
    {
        $event = $this->createEvent();
        $event->setReturnValue('foo');
        $this->assertEquals('foo', $event->getReturnValue(), '->getReturnValue() returns the return value of the event');
    }

    public function testSetIsProcessed()
    {
        $event = $this->createEvent();
        $event->setProcessed(true);
        $this->assertTrue($event->isProcessed(), '->isProcessed() returns true if the event has been processed');
        $event->setProcessed(false);
        $this->assertFalse($event->isProcessed(), '->setProcessed() changes the processed status');
    }

    public function testArrayAccessInterface()
    {
        $event = $this->createEvent();

        $this->assertEquals('bar', $event['foo'], 'Event implements the ArrayAccess interface');
        $event['foo'] = 'foo';
        $this->assertEquals('foo', $event['foo'], 'Event implements the ArrayAccess interface');

        try {
            $event['foobar'];
            $this->fail('::offsetGet() throws an \InvalidArgumentException exception when the parameter does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '::offsetGet() throws an \InvalidArgumentException exception when the parameter does not exist');
            $this->assertEquals('The event "name" has no "foobar" parameter.', $e->getMessage(), '::offsetGet() throws an \InvalidArgumentException exception when the parameter does not exist');
        }

        $this->assertTrue(isset($event['foo']), 'Event implements the ArrayAccess interface');
        unset($event['foo']);
        $this->assertFalse(isset($event['foo']), 'Event implements the ArrayAccess interface');
    }

    protected function createEvent()
    {
        $this->subject = new \stdClass();
        $this->parameters = array('foo' => 'bar');

        return new Event($this->subject, 'name', $this->parameters);
    }
}

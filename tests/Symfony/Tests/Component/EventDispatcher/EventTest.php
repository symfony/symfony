<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\EventDispatcher;

use Symfony\Component\EventDispatcher\Event;

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

        $this->assertEquals($this->parameters, $event->all(), '->all() returns the event parameters');
        $this->assertEquals('bar', $event->get('foo'), '->get() returns the value of a parameter');
        $event->set('foo', 'foo');
        $this->assertEquals('foo', $event->get('foo'), '->set() changes the value of a parameter');
        $this->assertTrue($event->has('foo'), '->has() returns true if the parameter is defined');
        $this->assertFalse($event->has('oof'), '->has() returns false if the parameter is not defined');

        try {
            $event->get('foobar');
            $this->fail('->get() throws an \InvalidArgumentException exception when the parameter does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->get() throws an \InvalidArgumentException exception when the parameter does not exist');
            $this->assertEquals('The event "name" has no "foobar" parameter.', $e->getMessage(), '->get() throws an \InvalidArgumentException exception when the parameter does not exist');
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

    protected function createEvent()
    {
        $this->subject = new \stdClass();
        $this->parameters = array('foo' => 'bar');

        return new Event($this->subject, 'name', $this->parameters);
    }
}

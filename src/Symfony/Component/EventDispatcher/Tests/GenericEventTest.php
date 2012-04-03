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

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Test class for Event.
 */
class GenericEventTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var GenericEvent
     */
    private $event;

    private $subject;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->subject = new \StdClass();
        $this->event = new GenericEvent($this->subject, array('name' => 'Event'), 'foo');
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->subject = null;
        $this->event = null;

        parent::tearDown();
    }

    public function test__construct()
    {
        $this->assertEquals($this->event, new GenericEvent($this->subject, array('name' => 'Event'), 'foo'));
    }

    /**
     * Tests Event->getArgs()
     */
    public function testGetArgs()
    {
        // test getting all
        $this->assertSame(array('name' => 'Event'), $this->event->getArgs());
    }

    public function testSetArgs()
    {
        $result = $this->event->setArgs(array('foo' => 'bar'));
        $this->assertAttributeSame(array('foo' => 'bar'), 'args', $this->event);
        $this->assertSame($this->event, $result);
    }

    public function testSetArg()
    {
        $result = $this->event->setArg('foo2', 'bar2');
        $this->assertAttributeSame(array('name' => 'Event', 'foo2' => 'bar2'), 'args', $this->event);
        $this->assertEquals($this->event, $result);
    }

    public function testGetArg()
    {
        // test getting key
        $this->assertEquals('Event', $this->event->getArg('name'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetArgException()
    {
        $this->event->getArg('nameNotExist');
    }

    public function testOffsetGet()
    {
        // test getting key
        $this->assertEquals('Event', $this->event['name']);

        // test getting invalid arg
        $this->setExpectedException('InvalidArgumentException');
        $this->assertFalse($this->event['nameNotExist']);
    }

    public function testOffsetSet()
    {
        $this->event['foo2'] = 'bar2';
        $this->assertAttributeSame(array('name' => 'Event', 'foo2' => 'bar2'), 'args', $this->event);
    }

    public function testOffsetUnset()
    {
        unset($this->event['name']);
        $this->assertAttributeSame(array(), 'args', $this->event);
    }

    public function testOffsetIsset()
    {
        $this->assertTrue(isset($this->event['name']));
        $this->assertFalse(isset($this->event['nameNotExist']));
    }

    public function testHasArg()
    {
        $this->assertTrue($this->event->hasArg('name'));
        $this->assertFalse($this->event->hasArg('nameNotExist'));
    }

    public function testGetSubject()
    {
        $this->assertSame($this->subject, $this->event->getSubject());
    }


    public function testGetData()
    {
        $this->event->setData("Don't drink and drive.");
        $this->assertEquals("Don't drink and drive.", $this->event->getData());
    }

    public function testSetData()
    {
        $this->event->setData("Don't drink and drive.");
        $this->assertEquals("Don't drink and drive.", $this->event->getData());
    }
}

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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Test class for Event.
 */
class GenericEventTest extends TestCase
{
    /**
     * @var GenericEvent
     */
    private $event;

    private $subject;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new \stdClass();
        $this->event = new GenericEvent($this->subject, array('name' => 'Event'));
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown(): void
    {
        $this->subject = null;
        $this->event = null;

        parent::tearDown();
    }

    public function testConstruct(): void
    {
        $this->assertEquals($this->event, new GenericEvent($this->subject, array('name' => 'Event')));
    }

    /**
     * Tests Event->getArgs().
     */
    public function testGetArguments(): void
    {
        // test getting all
        $this->assertSame(array('name' => 'Event'), $this->event->getArguments());
    }

    public function testSetArguments(): void
    {
        $result = $this->event->setArguments(array('foo' => 'bar'));
        $this->assertAttributeSame(array('foo' => 'bar'), 'arguments', $this->event);
        $this->assertSame($this->event, $result);
    }

    public function testSetArgument(): void
    {
        $result = $this->event->setArgument('foo2', 'bar2');
        $this->assertAttributeSame(array('name' => 'Event', 'foo2' => 'bar2'), 'arguments', $this->event);
        $this->assertEquals($this->event, $result);
    }

    public function testGetArgument(): void
    {
        // test getting key
        $this->assertEquals('Event', $this->event->getArgument('name'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetArgException(): void
    {
        $this->event->getArgument('nameNotExist');
    }

    public function testOffsetGet(): void
    {
        // test getting key
        $this->assertEquals('Event', $this->event['name']);

        // test getting invalid arg
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
        $this->assertFalse($this->event['nameNotExist']);
    }

    public function testOffsetSet(): void
    {
        $this->event['foo2'] = 'bar2';
        $this->assertAttributeSame(array('name' => 'Event', 'foo2' => 'bar2'), 'arguments', $this->event);
    }

    public function testOffsetUnset(): void
    {
        unset($this->event['name']);
        $this->assertAttributeSame(array(), 'arguments', $this->event);
    }

    public function testOffsetIsset(): void
    {
        $this->assertTrue(isset($this->event['name']));
        $this->assertFalse(isset($this->event['nameNotExist']));
    }

    public function testHasArgument(): void
    {
        $this->assertTrue($this->event->hasArgument('name'));
        $this->assertFalse($this->event->hasArgument('nameNotExist'));
    }

    public function testGetSubject(): void
    {
        $this->assertSame($this->subject, $this->event->getSubject());
    }

    public function testHasIterator(): void
    {
        $data = array();
        foreach ($this->event as $key => $value) {
            $data[$key] = $value;
        }
        $this->assertEquals(array('name' => 'Event'), $data);
    }
}

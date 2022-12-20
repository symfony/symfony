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
        $this->subject = new \stdClass();
        $this->event = new GenericEvent($this->subject, ['name' => 'Event']);
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown(): void
    {
        $this->subject = null;
        $this->event = null;
    }

    public function testConstruct()
    {
        self::assertEquals($this->event, new GenericEvent($this->subject, ['name' => 'Event']));
    }

    /**
     * Tests Event->getArgs().
     */
    public function testGetArguments()
    {
        // test getting all
        self::assertSame(['name' => 'Event'], $this->event->getArguments());
    }

    public function testSetArguments()
    {
        $result = $this->event->setArguments(['foo' => 'bar']);
        self::assertSame(['foo' => 'bar'], $this->event->getArguments());
        self::assertSame($this->event, $result);
    }

    public function testSetArgument()
    {
        $result = $this->event->setArgument('foo2', 'bar2');
        self::assertSame(['name' => 'Event', 'foo2' => 'bar2'], $this->event->getArguments());
        self::assertEquals($this->event, $result);
    }

    public function testGetArgument()
    {
        // test getting key
        self::assertEquals('Event', $this->event->getArgument('name'));
    }

    public function testGetArgException()
    {
        self::expectException(\InvalidArgumentException::class);
        $this->event->getArgument('nameNotExist');
    }

    public function testOffsetGet()
    {
        // test getting key
        self::assertEquals('Event', $this->event['name']);

        // test getting invalid arg
        self::expectException(\InvalidArgumentException::class);
        self::assertFalse($this->event['nameNotExist']);
    }

    public function testOffsetSet()
    {
        $this->event['foo2'] = 'bar2';
        self::assertSame(['name' => 'Event', 'foo2' => 'bar2'], $this->event->getArguments());
    }

    public function testOffsetUnset()
    {
        unset($this->event['name']);
        self::assertSame([], $this->event->getArguments());
    }

    public function testOffsetIsset()
    {
        self::assertArrayHasKey('name', $this->event);
        self::assertArrayNotHasKey('nameNotExist', $this->event);
    }

    public function testHasArgument()
    {
        self::assertTrue($this->event->hasArgument('name'));
        self::assertFalse($this->event->hasArgument('nameNotExist'));
    }

    public function testGetSubject()
    {
        self::assertSame($this->subject, $this->event->getSubject());
    }

    public function testHasIterator()
    {
        $data = [];
        foreach ($this->event as $key => $value) {
            $data[$key] = $value;
        }
        self::assertEquals(['name' => 'Event'], $data);
    }
}

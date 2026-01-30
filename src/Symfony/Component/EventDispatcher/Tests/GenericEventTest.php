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
    private GenericEvent $event;
    private \stdClass $subject;

    protected function setUp(): void
    {
        $this->subject = new \stdClass();
        $this->event = new GenericEvent($this->subject, ['name' => 'Event']);
    }

    public function testConstruct(): void
    {
        $this->assertEquals($this->event, new GenericEvent($this->subject, ['name' => 'Event']));
    }

    /**
     * Tests Event->getArgs().
     */
    public function testGetArguments(): void
    {
        // test getting all
        $this->assertSame(['name' => 'Event'], $this->event->getArguments());
    }

    public function testSetArguments(): void
    {
        $result = $this->event->setArguments(['foo' => 'bar']);
        $this->assertSame(['foo' => 'bar'], $this->event->getArguments());
        $this->assertSame($this->event, $result);
    }

    public function testSetArgument(): void
    {
        $result = $this->event->setArgument('foo2', 'bar2');
        $this->assertSame(['name' => 'Event', 'foo2' => 'bar2'], $this->event->getArguments());
        $this->assertEquals($this->event, $result);
    }

    public function testGetArgument(): void
    {
        // test getting key
        $this->assertEquals('Event', $this->event->getArgument('name'));
    }

    public function testGetArgException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->event->getArgument('nameNotExist');
    }

    public function testOffsetGet(): void
    {
        // test getting key
        $this->assertEquals('Event', $this->event['name']);

        // test getting invalid arg
        $this->expectException(\InvalidArgumentException::class);
        $this->assertFalse($this->event['nameNotExist']);
    }

    public function testOffsetSet(): void
    {
        $this->event['foo2'] = 'bar2';
        $this->assertSame(['name' => 'Event', 'foo2' => 'bar2'], $this->event->getArguments());
    }

    public function testOffsetUnset(): void
    {
        unset($this->event['name']);
        $this->assertSame([], $this->event->getArguments());
    }

    public function testOffsetIsset(): void
    {
        $this->assertArrayHasKey('name', $this->event);
        $this->assertArrayNotHasKey('nameNotExist', $this->event);
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
        $data = [];
        foreach ($this->event as $key => $value) {
            $data[$key] = $value;
        }
        $this->assertEquals(['name' => 'Event'], $data);
    }
}

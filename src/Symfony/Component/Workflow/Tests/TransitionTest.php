<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Transition;

class TransitionTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidArgumentException
     * @expectedExceptionMessage The transition "foo.bar" contains invalid characters.
     */
    public function testValidateName()
    {
        $transition = new Transition('foo.bar', 'a', 'b');
    }

    public function testConstructor()
    {
        $transition = new Transition('name', 'a', 'b');

        $this->assertSame('name', $transition->getName());
        $this->assertSame(array('a'), $transition->getFroms());
        $this->assertSame(array('b'), $transition->getTos());
    }
}

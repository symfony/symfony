<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Transition;

class TransitionTest extends TestCase
{
    public function testValidateName()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The transition "foo.bar" contains invalid characters.');
        new Transition('foo.bar', 'a', 'b');
    }

    public function testConstructor()
    {
        $transition = new Transition('name', 'a', 'b');

        $this->assertSame('name', $transition->getName());
        $this->assertSame(['a'], $transition->getFroms());
        $this->assertSame(['b'], $transition->getTos());
    }
}

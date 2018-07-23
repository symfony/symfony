<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Transition;

class TransitionTest extends TestCase
{
    public function testConstructor()
    {
        $transition = new Transition('name', 'a', 'b');

        $this->assertSame('name', $transition->getName());
        $this->assertSame(array('a'), $transition->getFroms());
        $this->assertSame(array('b'), $transition->getTos());
    }
}

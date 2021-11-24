<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Tests\fixtures\FooEnum;
use Symfony\Component\Workflow\Transition;

class TransitionTest extends TestCase
{
    public function testConstructorWithStrings()
    {
        $transition = new Transition('name', 'a', 'b');

        $this->assertSame('name', $transition->getName());
        $this->assertSame(['a'], $transition->getFroms());
        $this->assertSame(['b'], $transition->getTos());
    }

    /**
     * @requires PHP 8.1
     */
    public function testConstructorWithEnumerations()
    {
        $transition = new Transition('name', FooEnum::Bar, FooEnum::Baz);

        $this->assertSame('name', $transition->getName());
        $this->assertSame([FooEnum::Bar], $transition->getFroms());
        $this->assertSame([FooEnum::Baz], $transition->getTos());
    }
}

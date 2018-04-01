<?php

namespace Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype;

class Foo implements FooInterface, Sub\BarInterface
{
    public function __construct($bar = null)
    {
    }

    public function setFoo(self $foo)
    {
    }
}

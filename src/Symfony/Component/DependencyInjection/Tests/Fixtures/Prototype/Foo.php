<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'prod')]
#[When(env: 'dev')]
class Foo implements FooInterface, Sub\BarInterface
{
    public function __construct($bar = null, iterable $foo = null)
    {
    }

    public function setFoo(self $foo)
    {
    }
}

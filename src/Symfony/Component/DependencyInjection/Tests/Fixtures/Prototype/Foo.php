<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

use Symfony\Component\DependencyInjection\Attribute\When;

#[ProductionOnly]
#[When(env: 'dev')]
class Foo implements FooInterface, Sub\BarInterface
{
    public function __construct($bar = null, iterable $foo = null, object $baz = null)
    {
    }

    public function setFoo(self $foo)
    {
    }
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
class ProductionOnly extends When
{
    public function __construct()
    {
        parent::__construct('prod');
    }
}

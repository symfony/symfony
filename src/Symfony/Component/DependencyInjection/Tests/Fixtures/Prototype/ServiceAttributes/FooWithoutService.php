<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\ServiceAttributes;

class FooWithoutService
{
    public function __construct($bar = null, ?iterable $foo = null, ?object $baz = null)
    {
    }

    public function setFoo(self $foo)
    {
    }
}

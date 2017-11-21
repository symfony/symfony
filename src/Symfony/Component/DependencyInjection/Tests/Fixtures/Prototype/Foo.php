<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

class Foo
{
    public function __construct($bar = null)
    {
    }

    function setFoo(self $foo)
    {
    }
}

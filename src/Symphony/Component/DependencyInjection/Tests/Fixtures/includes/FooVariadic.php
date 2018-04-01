<?php

namespace Symphony\Component\DependencyInjection\Tests\Fixtures\includes;

use Symphony\Component\DependencyInjection\Tests\Compiler\Foo;

class FooVariadic
{
    public function __construct(Foo $foo)
    {
    }

    public function bar(...$arguments)
    {
    }
}

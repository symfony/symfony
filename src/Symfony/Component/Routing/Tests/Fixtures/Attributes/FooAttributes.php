<?php

namespace Symfony\Component\Routing\Tests\Fixtures\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class FooAttributes
{
    public string $class;
    public array $foo = [];

    public function __construct(string $class, array $foo)
    {
        $this->class = $class;
        $this->foo = $foo;
    }
}

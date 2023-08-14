<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooClassWithDefaultArrayAttribute
{
    public function __construct(
        array $array = ['a', 'b', 'c'],
        bool $firstOptional = false,
        bool $secondOptional = false
    ) {}
}

<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooClassWithDefaultObjectAttribute
{
    public function __construct(
        object $object = new \stdClass(),
        bool $firstOptional = false,
        bool $secondOptional = false,
    ) {}
}

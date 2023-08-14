<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooClassWithDefaultEnumAttribute
{
    public function __construct(
        FooUnitEnum $enum = FooUnitEnum::FOO,
        bool $firstOptional = false,
        bool $secondOptional = false,
    ) {}
}

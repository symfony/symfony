<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooEnumeratedTagClass
{
    public static function getDefaultFooName()
    {
        return FooBackedEnum::BAR;
    }
}

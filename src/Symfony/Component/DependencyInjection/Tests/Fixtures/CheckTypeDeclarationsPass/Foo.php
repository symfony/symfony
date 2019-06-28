<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class Foo
{
    public static function createBar()
    {
        return new Bar(new \stdClass());
    }

    public static function createBarArguments(\stdClass $stdClass, \stdClass $stdClassOptional = null)
    {
        return new Bar($stdClass);
    }
}
